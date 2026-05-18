import { ref, onUnmounted } from 'vue';

export type CallStatus = 'idle' | 'ringing' | 'connecting' | 'connected' | 'ended';

export interface CallState {
    status: CallStatus;
    peerId: number | null;
    peerName: string;
    isCaller: boolean;
    timer: number;
    wasConnected: boolean;
}

export function useCall() {
    const state = ref<CallState>({
        status: 'idle',
        peerId: null,
        peerName: '',
        isCaller: false,
        timer: 0,
        wasConnected: false,
    });

    let pc: RTCPeerConnection | null = null;
    let localStream: MediaStream | null = null;
    let remoteStream: MediaStream | null = null;
    let timerInterval: ReturnType<typeof setInterval> | null = null;
    let pendingCandidates: RTCIceCandidateInit[] = [];

    function setStatus(s: CallStatus) {
        state.value = { ...state.value, status: s };
    }

    async function startCall(peerId: number, peerName: string, echo: any) {
        if (!echo) return;

        state.value = { ...state.value, peerId, peerName, isCaller: true, timer: 0, wasConnected: false };
        setStatus('connecting');

        try {
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
        } catch {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch {
                setStatus('idle');
                return;
            }
        }

        const servers: RTCConfiguration = {
            iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
        };

        pc = new RTCPeerConnection(servers);

        for (const track of localStream.getTracks()) {
            pc.addTrack(track, localStream);
        }

        pc.onicecandidate = (e) => {
            if (e.candidate) {
                sendSignal(peerId, 'ice-candidate', e.candidate.toJSON(), echo);
            }
        };

        pc.ontrack = (e) => {
            remoteStream = e.streams[0];
            state.value = { ...state.value, wasConnected: true };
            setStatus('connected');
            startTimer();
        };

        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            sendSignal(peerId, 'offer', offer, echo);
        } catch {
            setStatus('idle');
            cleanup();
        }
    }

    async function receiveOffer(
        senderId: number,
        senderName: string,
        data: any,
        echo: any,
        onIncoming: (name: string, onAccept: () => void, onDecline: () => void) => void,
    ) {
        state.value = { ...state.value, peerId: senderId, peerName: senderName, isCaller: false, timer: 0, wasConnected: false };

        onIncoming(senderName, async () => {
            setStatus('connecting');

            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
            } catch {
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                } catch {
                    sendSignal(senderId, 'end', null, echo);
                    setStatus('idle');
                    return;
                }
            }

            const servers: RTCConfiguration = {
                iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
            };

            pc = new RTCPeerConnection(servers);

            for (const track of localStream.getTracks()) {
                pc.addTrack(track, localStream);
            }

            pc.onicecandidate = (e) => {
                if (e.candidate) {
                    sendSignal(senderId, 'ice-candidate', e.candidate.toJSON(), echo);
                }
            };

            pc.ontrack = (e) => {
                remoteStream = e.streams[0];
                state.value = { ...state.value, wasConnected: true };
                setStatus('connected');
                startTimer();
            };

            try {
                await pc.setRemoteDescription(new RTCSessionDescription(data));
                for (const c of pendingCandidates) {
                    await pc.addIceCandidate(new RTCIceCandidate(c));
                }
                pendingCandidates = [];

                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                sendSignal(senderId, 'answer', answer, echo);
            } catch {
                setStatus('idle');
                cleanup();
            }
        }, () => {
            sendSignal(senderId, 'end', null, echo);
            setStatus('idle');
        });
    }

    async function receiveAnswer(data: any) {
        if (!pc) return;

        try {
            await pc.setRemoteDescription(new RTCSessionDescription(data));
            for (const c of pendingCandidates) {
                await pc.addIceCandidate(new RTCIceCandidate(c));
            }
            pendingCandidates = [];
        } catch {
            // ignore
        }
    }

    async function receiveIceCandidate(data: any) {
        if (!pc || !pc.remoteDescription) {
            pendingCandidates.push(data);
            return;
        }

        try {
            await pc.addIceCandidate(new RTCIceCandidate(data));
        } catch {
            // ignore
        }
    }

    function sendSignal(recipientId: number, type: string, data: any, echo: any) {
        fetch('/calls/signal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window as any).csrfToken },
            body: JSON.stringify({ recipient_id: recipientId, type, data }),
        }).catch(() => {});
    }

    function endCall(peerId: number, peerName: string, echo: any, csrfToken: string) {
        sendSignal(peerId, 'end', null, echo);

        if (!state.value.wasConnected) {
            fetch('/calls/missed', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ recipient_id: peerId }),
            }).catch(() => {});
        }

        setStatus('ended');
        cleanup();
        setTimeout(() => setStatus('idle'), 1500);
    }

    function remoteEnded(csrfToken: string) {
        if (!state.value.wasConnected && state.value.peerId) {
            fetch('/calls/missed', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ recipient_id: state.value.peerId }),
            }).catch(() => {});
        }

        setStatus('ended');
        cleanup();
        setTimeout(() => setStatus('idle'), 1500);
    }

    function startTimer() {
        timerInterval = setInterval(() => {
            state.value = { ...state.value, timer: state.value.timer + 1 };
        }, 1000);
    }

    function cleanup() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }

        if (localStream) {
            localStream.getTracks().forEach(t => t.stop());
            localStream = null;
        }

        remoteStream = null;

        if (pc) {
            pc.close();
            pc = null;
        }

        pendingCandidates = [];
    }

    function formatTimer(seconds: number): string {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    onUnmounted(() => {
        cleanup();
    });

    return {
        state,
        startCall,
        receiveOffer,
        receiveAnswer,
        receiveIceCandidate,
        endCall,
        remoteEnded,
        formatTimer,
        cleanup,
    };
}
