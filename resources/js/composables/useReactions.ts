import { ref } from 'vue';

export interface ReactionData {
    id: number;
    user_id: number;
    reaction: string;
    user: { id: number; name: string };
}

export const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

export function useReactions() {
    const showReactionPicker = ref<number | null>(null);

    function toggleReactionPicker(msgId: number) {
        showReactionPicker.value = showReactionPicker.value === msgId ? null : msgId;
    }

    function closeReactionPicker() {
        showReactionPicker.value = null;
    }

    function sendReaction(msgId: number, reaction: string, csrfToken: string) {
        fetch(`/messages/${msgId}/reactions`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ reaction }),
        }).catch(() => {});

        showReactionPicker.value = null;
    }

    function hasUserReacted(reactions: ReactionData[] | undefined, userId: number, reaction: string): boolean {
        if (!reactions) return false;
        return reactions.some(r => r.user_id === userId && r.reaction === reaction);
    }

    function countReactions(reactions: ReactionData[] | undefined, reaction: string): number {
        if (!reactions) return 0;
        return reactions.filter(r => r.reaction === reaction).length;
    }

    function uniqueReactions(reactions: ReactionData[] | undefined): { reaction: string; count: number; users: string[] }[] {
        if (!reactions) return [];

        const map = new Map<string, { count: number; users: string[] }>();

        for (const r of reactions) {
            const existing = map.get(r.reaction);
            if (existing) {
                existing.count++;
                if (!existing.users.includes(r.user.name)) {
                    existing.users.push(r.user.name);
                }
            } else {
                map.set(r.reaction, { count: 1, users: [r.user.name] });
            }
        }

        return Array.from(map.entries()).map(([reaction, data]) => ({
            reaction,
            count: data.count,
            users: data.users,
        }));
    }

    return {
        showReactionPicker,
        toggleReactionPicker,
        closeReactionPicker,
        sendReaction,
        hasUserReacted,
        countReactions,
        uniqueReactions,
    };
}
