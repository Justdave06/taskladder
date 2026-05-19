<script setup lang="ts">
import { ref, computed, nextTick, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Bold, Italic, Underline, Highlighter, Check,
    AlignLeft, AlignCenter, AlignRight, AlignJustify,
    Save, Plus, Trash2, FileText, Printer, Send,
    Image as ImageIcon, ChevronDown, X,
    ChevronLeft, ChevronRight,
} from 'lucide-vue-next';

interface Report {
    id: number;
    title: string;
    content: string;
    updated_at: string;
    user_id: number;
}

const PAGE_HEIGHT = 620;
const PAGE_CONTENT_W = 720;

const FONT_SIZES = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 36, 48, 72];

const page = usePage();
const reports = ref<Report[]>((page.props as any).reports ?? []);
const activeReportId = ref<number | null>(null);
const reportTitle = ref('Untitled');
const editorRef = ref<HTMLElement | null>(null);
const imageInputRef = ref<HTMLInputElement | null>(null);
const showFontSize = ref(false);
const csrfToken = (page.props as any).csrf_token as string;
const isDirty = ref(false);
const isSaving = ref(false);
const pendingImageUrl = ref('');
const showWidthInput = ref(false);
const imageWidth = ref(400);
const imageWidthInputRef = ref<HTMLInputElement | null>(null);
const showSendDialog = ref(false);
const sendRecipient = ref<number | null>(null);
const isSending = ref(false);
const sendMsg = ref('');

const users = ref<{ id: number; name: string }[]>((page.props as any).users ?? []);
const authUser = (page.props.auth as any).user as { id: number };
const isReadOnly = computed(() => {
    if (!activeReportId.value) return false;
    const report = reports.value.find(r => r.id === activeReportId.value);
    return report ? report.user_id !== authUser.id : false;
});

const pages = ref<string[]>(['']);
const currentPageIdx = ref(0);

function initPages(content: string) {
    if (content.includes('<!--pb-->')) {
        pages.value = content.split('<!--pb-->');
    } else if (content) {
        pages.value = [content];
    } else {
        pages.value = [''];
    }
    currentPageIdx.value = 0;
}

function loadEditorContent() {
    if (editorRef.value) {
        editorRef.value.innerHTML = pages.value[currentPageIdx.value] || '';
    }
}

function saveCurrentPageContent() {
    if (editorRef.value) {
        pages.value[currentPageIdx.value] = editorRef.value.innerHTML;
    }
}

function restoreCursor() {
    const el = editorRef.value;
    if (!el) return;
    el.focus();
    const sel = window.getSelection();
    if (!sel) return;
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
}

function newReport() {
    activeReportId.value = null;
    reportTitle.value = 'Untitled';
    isDirty.value = false;
    pages.value = [''];
    currentPageIdx.value = 0;
    nextTick(loadEditorContent);
}

function selectReport(report: Report) {
    activeReportId.value = report.id;
    reportTitle.value = report.title;
    isDirty.value = false;

    fetch(`/ledger/${report.id}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    })
        .then(r => r.json())
        .then((data: Report) => {
            initPages(data.content);
            nextTick(loadEditorContent);
        });
}

function execCmd(command: string, value?: string) {
    document.execCommand(command, false, value);
    editorRef.value?.focus();
    nextTick(checkOverflow);
}

function onEditorInput() {
    isDirty.value = true;
    nextTick(checkOverflow);
}

function onTitleInput(e: Event) {
    reportTitle.value = (e.target as HTMLInputElement).value;
    isDirty.value = true;
}

function checkOverflow() {
    const el = editorRef.value;
    if (!el) return;

    saveCurrentPageContent();
    if (el.scrollHeight > PAGE_HEIGHT + 30) {
        splitPage();
        restoreCursor();
    }
}

function splitPage() {
    const el = editorRef.value;
    if (!el) return;
    if (el.scrollHeight <= PAGE_HEIGHT + 30) return;

    const fullHtml = el.innerHTML;

    const temp = document.createElement('div');
    temp.innerHTML = fullHtml;
    temp.style.cssText = `font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.6; width: ${PAGE_CONTENT_W - 96}px; padding: 32px 48px;`;
    temp.style.position = 'absolute';
    temp.style.left = '-9999px';
    document.body.appendChild(temp);

    const removed: ChildNode[] = [];
    while (temp.scrollHeight > PAGE_HEIGHT && temp.lastChild) {
        removed.unshift(temp.removeChild(temp.lastChild));
    }

    if (removed.length > 0) {
        pages.value[currentPageIdx.value] = temp.innerHTML;
        const overflowHtml = removed.map(n =>
            n.nodeType === 1 ? (n as Element).outerHTML : (n.textContent || ''),
        ).join('');

        if (currentPageIdx.value + 1 < pages.value.length) {
            pages.value[currentPageIdx.value + 1] = overflowHtml + pages.value[currentPageIdx.value + 1];
        } else {
            pages.value.push(overflowHtml);
        }
        el.innerHTML = pages.value[currentPageIdx.value];
    }

    document.body.removeChild(temp);
}

function addPage() {
    saveCurrentPageContent();
    pages.value.push('');
    currentPageIdx.value = pages.value.length - 1;
    nextTick(loadEditorContent);
}

function switchPage(idx: number) {
    if (idx === currentPageIdx.value) return;
    saveCurrentPageContent();
    currentPageIdx.value = idx;
    nextTick(() => { loadEditorContent(); nextTick(checkOverflow); });
}

function setFontSize(pt: number) {
    showFontSize.value = false;
    const sel = window.getSelection();
    if (!sel?.rangeCount) return;

    if (!sel.isCollapsed) {
        const range = sel.getRangeAt(0);
        const span = document.createElement('span');
        span.style.fontSize = pt + 'pt';
        try {
            span.appendChild(range.extractContents());
            range.insertNode(span);
        } catch { /* ignore */ }
    } else {
        document.execCommand('fontSize', false, '7');
        const editor = editorRef.value;
        if (editor) {
            const fonts = editor.querySelectorAll('font[size="7"]');
            fonts.forEach(f => {
                const s = document.createElement('span');
                s.style.fontSize = pt + 'pt';
                while (f.firstChild) s.appendChild(f.firstChild);
                f.parentNode?.replaceChild(s, f);
            });
        }
    }
    editorRef.value?.focus();
    nextTick(checkOverflow);
}

function attachImage() {
    imageInputRef.value?.click();
}

function onImageSelected(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    input.value = '';

    const formData = new FormData();
    formData.append('image', file);

    fetch('/ledger/images', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        body: formData,
    })
        .then(r => r.json())
        .then((data: { url: string }) => {
            pendingImageUrl.value = data.url;
            imageWidth.value = Math.min(400, PAGE_CONTENT_W);
            showWidthInput.value = true;
            nextTick(() => imageWidthInputRef.value?.focus());
        });
}

function confirmImageWidth() {
    const el = editorRef.value;
    if (!el || !pendingImageUrl.value) return;
    el.focus();
    document.execCommand('insertHTML', false, `<img src="${pendingImageUrl.value}" width="${imageWidth.value}" style="max-width:100%;height:auto;border-radius:4px;" />`);
    pendingImageUrl.value = '';
    showWidthInput.value = false;
    nextTick(checkOverflow);
}

function saveReport() {
    saveCurrentPageContent();
    isSaving.value = true;

    const content = pages.value.join('<!--pb-->');
    const url = activeReportId.value ? `/ledger/${activeReportId.value}` : '/ledger';
    const method = activeReportId.value ? 'PATCH' : 'POST';

    fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ title: reportTitle.value, content }),
    })
        .then(r => r.json())
        .then((saved: Report) => {
            activeReportId.value = saved.id;
            const idx = reports.value.findIndex(r => r.id === saved.id);
            if (idx !== -1) {
                reports.value[idx] = { ...reports.value[idx], ...saved };
            } else {
                reports.value.push({ id: saved.id, title: saved.title, content: saved.content, updated_at: saved.updated_at });
            }
            isDirty.value = false;
        })
        .finally(() => { isSaving.value = false; });
}

function deleteReport(report: Report) {
    if (!confirm('Delete this report?')) return;

    fetch(`/ledger/${report.id}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    }).then(() => {
        reports.value = reports.value.filter(r => r.id !== report.id);
        if (activeReportId.value === report.id) newReport();
    });
}

function sendReport() {
    if (!sendRecipient.value || !activeReportId.value) return;
    isSending.value = true;

    fetch(`/ledger/${activeReportId.value}/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            recipient_id: sendRecipient.value,
            note: sendMsg.value,
        }),
    })
        .then(r => {
            if (!r.ok) throw new Error('Send failed');
            showSendDialog.value = false;
            sendRecipient.value = null;
            sendMsg.value = '';
        })
        .catch(() => alert('Failed to send. Try again.'))
        .finally(() => { isSending.value = false; });
}

function printReport() {
    saveCurrentPageContent();
    const content = pages.value.join('<div style="page-break-before: always;"></div>');
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${reportTitle.value}</title><style>body{font-family:Arial,sans-serif;font-size:12pt;line-height:1.6;margin:1in;color:#000;}</style></head><body>${content}</body></html>`);
    w.document.close();
    w.print();
}

onMounted(() => {
    const url = page.url;
    const match = url.match(/[?&]open=(\d+)/);
    if (match) {
        const id = parseInt(match[1], 10);
        const report = reports.value.find(r => r.id === id);
        if (report) selectReport(report);
    }
});
</script>

<template>
    <div class="flex h-[calc(100vh-8rem)] gap-4">
        <!-- Sidebar -->
        <div class="w-56 shrink-0 overflow-y-auto rounded-xl border border-[#E4E7F0] bg-white p-3">
            <div class="mb-3 flex items-center justify-between">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-[#9BA3B8]">MY LEDGER</span>
                <button
                    class="flex h-6 w-6 items-center justify-center rounded-md text-[#9BA3B8] transition hover:bg-[#F0F2F8] hover:text-[#2563EB]"
                    title="New Report"
                    @click="newReport"
                >
                    <Plus class="h-4 w-4" />
                </button>
            </div>
            <div class="space-y-0.5">
                <button
                    v-for="report in reports"
                    :key="report.id"
                    class="group flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-[12px] transition"
                    :class="activeReportId === report.id ? 'bg-[#2563EB]/10 font-medium text-[#2563EB]' : 'text-[#0F1623] hover:bg-[#F0F2F8]'"
                    @click="selectReport(report)"
                >
                    <FileText class="h-3.5 w-3.5 shrink-0 opacity-60" />
                    <span class="truncate flex-1">{{ report.title }}</span>
                    <span v-if="report.user_id !== authUser.id" class="rounded bg-[#E4E7F0] px-1.5 py-0.5 text-[8px] font-medium text-[#5A6278]">Shared</span>
                    <button
                        v-if="report.user_id === authUser.id"
                        class="hidden shrink-0 text-[#9BA3B8] hover:text-red-500 group-hover:block"
                        title="Delete"
                        @click.stop="deleteReport(report)"
                    >
                        <Trash2 class="h-3 w-3" />
                    </button>
                </button>
                <div v-if="reports.length === 0" class="py-8 text-center text-[11px] text-[#9BA3B8]">
                    No reports yet
                </div>
            </div>
        </div>

        <!-- Editor -->
        <div class="flex flex-1 flex-col overflow-hidden rounded-xl bg-[#E4E7F0]">
            <!-- Title bar -->
            <div class="flex items-center gap-3 border-b border-[#D0D4E0] bg-white px-5 py-2.5">
                <input
                    :value="reportTitle"
                    class="flex-1 border-0 bg-transparent text-[13px] font-semibold text-[#0F1623] outline-none"
                    placeholder="Report title…"
                    @input="onTitleInput"
                />
                <div class="flex items-center gap-2">
                    <span v-if="isDirty" class="text-[10px] text-[#9BA3B8]">Unsaved</span>
                    <button
                        class="inline-flex items-center gap-1 rounded-lg border border-[#D0D4E0] px-2.5 py-1.5 text-[11px] font-medium text-[#5A6278] transition hover:bg-[#F0F2F8] disabled:opacity-50"
                        title="Print"
                        :disabled="!activeReportId"
                        @click="printReport"
                    >
                        <Printer class="h-3.5 w-3.5" />
                    </button>
                    <a
                        v-if="activeReportId"
                        :href="`/ledger/${activeReportId}/export`"
                        class="inline-flex items-center gap-1 rounded-lg border border-[#D0D4E0] px-2.5 py-1.5 text-[11px] font-medium text-[#5A6278] transition hover:bg-[#F0F2F8]"
                        title="Export to Word"
                    >
                        <FileText class="h-3.5 w-3.5" />
                        Export
                    </a>
                    <button
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#2563EB] px-3 py-1.5 text-[11px] font-medium text-white transition hover:bg-[#1d4ed8] disabled:opacity-50"
                        :disabled="isSaving || !reportTitle.trim()"
                        @click="saveReport"
                    >
                        <Save class="h-3.5 w-3.5" />
                        {{ isSaving ? 'Saving…' : 'Save' }}
                    </button>
                    <button
                        class="inline-flex items-center gap-1 rounded-lg bg-[#059669] px-3 py-1.5 text-[11px] font-medium text-white transition hover:bg-[#047857] disabled:opacity-50"
                        :disabled="!activeReportId"
                        title="Send to Member"
                        @click="showSendDialog = true"
                    >
                        <Send class="h-3.5 w-3.5" />
                        Send
                    </button>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="flex items-center gap-1 border-b border-[#D0D4E0] bg-white px-4 py-2">
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Bold (Ctrl+B)" @click="execCmd('bold')">
                    <Bold class="h-4 w-4" />
                </button>
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Italic (Ctrl+I)" @click="execCmd('italic')">
                    <Italic class="h-4 w-4" />
                </button>
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Underline (Ctrl+U)" @click="execCmd('underline')">
                    <Underline class="h-4 w-4" />
                </button>
                <span class="mx-1 h-5 w-px bg-[#E4E7F0]" />

                <!-- Font size -->
                <div class="relative">
                    <button
                        class="flex h-8 items-center gap-1 rounded-md px-2 text-[11px] text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none"
                        :disabled="isReadOnly"
                        title="Font Size"
                        @click="showFontSize = !showFontSize"
                    >
                        12
                        <ChevronDown class="h-3 w-3" />
                    </button>
                    <div v-if="showFontSize && !isReadOnly" class="absolute left-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-lg border border-[#E4E7F0] bg-white py-1 shadow-lg">
                        <button
                            v-for="s in FONT_SIZES"
                            :key="s"
                            class="flex w-full items-center px-3 py-1 text-[11px] text-[#0F1623] transition hover:bg-[#F0F2F8]"
                            @click="setFontSize(s)"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>

                <span class="mx-1 h-5 w-px bg-[#E4E7F0]" />
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Highlight" @click="execCmd('hiliteColor', '#FDE047')">
                    <Highlighter class="h-4 w-4" />
                </button>
                <span class="mx-1 h-5 w-px bg-[#E4E7F0]" />
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Align Left" @click="execCmd('justifyLeft')">
                    <AlignLeft class="h-4 w-4" />
                </button>
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Align Center" @click="execCmd('justifyCenter')">
                    <AlignCenter class="h-4 w-4" />
                </button>
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Align Right" @click="execCmd('justifyRight')">
                    <AlignRight class="h-4 w-4" />
                </button>
                <button class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none" :disabled="isReadOnly" title="Justify" @click="execCmd('justifyFull')">
                    <AlignJustify class="h-4 w-4" />
                </button>
                <span class="mx-1 h-5 w-px bg-[#E4E7F0]" />
                <button
                    class="flex h-8 w-8 items-center justify-center rounded-md text-[#0F1623] transition hover:bg-[#F0F2F8] disabled:opacity-30 disabled:pointer-events-none"
                    :disabled="isReadOnly"
                    title="Insert Image"
                    @click="attachImage"
                >
                    <ImageIcon class="h-4 w-4" />
                </button>
                <input ref="imageInputRef" type="file" accept="image/*" class="hidden" @change="onImageSelected" />

                <!-- Image width input -->
                <div v-if="showWidthInput" class="ml-2 flex items-center gap-1.5 rounded-md border border-[#E4E7F0] bg-[#FAFBFC] px-2.5 py-1">
                    <span class="text-[10px] text-[#5A6278]">W:</span>
                    <input
                        ref="imageWidthInputRef"
                        v-model.number="imageWidth"
                        type="number"
                        min="50"
                        :max="PAGE_CONTENT_W"
                        class="w-14 rounded border border-[#E4E7F0] px-1.5 py-0.5 text-[11px] outline-none focus:border-[#2563EB]"
                        @keydown.enter="confirmImageWidth"
                    />
                    <span class="text-[10px] text-[#9BA3B8]">px</span>
                    <button class="flex h-6 w-6 items-center justify-center rounded text-[#2563EB] transition hover:bg-[#2563EB]/10" title="Insert" @click="confirmImageWidth">
                        <Check class="h-3.5 w-3.5" />
                    </button>
                    <button class="flex h-6 w-6 items-center justify-center rounded text-[#9BA3B8] transition hover:bg-[#F0F2F8] hover:text-red-500" title="Cancel" @click="showWidthInput = false; pendingImageUrl = ''">
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
                <span v-if="isReadOnly" class="ml-auto text-[10px] font-medium text-[#F59E0B]">View-only</span>
            </div>

            <!-- Paper area -->
            <div class="flex flex-1 flex-col items-center overflow-y-auto bg-[#E4E7F0] px-8 py-6">
                <div
                    ref="editorRef"
                    :style="{ height: PAGE_HEIGHT + 'px', fontFamily: 'Arial, sans-serif', fontSize: '12pt', lineHeight: '1.6', maxWidth: PAGE_CONTENT_W + 'px' }"
                    class="w-full overflow-hidden rounded-xl bg-white px-12 py-8 shadow-md outline-none"
                    :contenteditable="!isReadOnly"
                    @input="onEditorInput"
                />
                <!-- Page footer -->
                <div class="mt-3 flex items-center gap-3">
                    <button
                        class="flex h-7 w-7 items-center justify-center rounded-md text-[#5A6278] transition hover:bg-white/60 disabled:opacity-30"
                        :disabled="currentPageIdx === 0"
                        @click="switchPage(currentPageIdx - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-[11px] font-medium text-[#5A6278]">
                        Page {{ currentPageIdx + 1 }} of {{ pages.length }}
                    </span>
                    <button
                        class="flex h-7 w-7 items-center justify-center rounded-md text-[#5A6278] transition hover:bg-white/60 disabled:opacity-30"
                        :disabled="currentPageIdx === pages.length - 1"
                        @click="switchPage(currentPageIdx + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                    <button
                        class="ml-2 inline-flex items-center gap-1 rounded-lg border border-[#D0D4E0] px-3 py-1 text-[11px] font-medium text-[#5A6278] transition hover:bg-white/60"
                        @click="addPage"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Add Page
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Send dialog -->
    <Teleport to="body">
        <div v-if="showSendDialog" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40" @click.self="showSendDialog = false">
            <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-[13px] font-semibold text-[#0F1623]">Send Report</span>
                    <button class="text-[#9BA3B8] hover:text-[#0F1623]" @click="showSendDialog = false">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <p class="mb-3 text-[11px] text-[#5A6278]">"{{ reportTitle }}" will be sent as a .doc file.</p>
                <div class="mb-3 space-y-2">
                    <label class="text-[10px] font-medium uppercase tracking-wider text-[#9BA3B8]">Recipient</label>
                    <select
                        v-model="sendRecipient"
                        class="w-full rounded-lg border border-[#E4E7F0] px-3 py-2 text-[12px] text-[#0F1623] outline-none focus:border-[#2563EB]"
                    >
                        <option :value="null" disabled>Select a member…</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="mb-4 space-y-2">
                    <label class="text-[10px] font-medium uppercase tracking-wider text-[#9BA3B8]">Message (optional)</label>
                    <textarea
                        v-model="sendMsg"
                        class="w-full resize-none rounded-lg border border-[#E4E7F0] px-3 py-2 text-[12px] text-[#0F1623] outline-none focus:border-[#2563EB]"
                        rows="2"
                        placeholder="Add a note…"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        class="rounded-lg border border-[#E4E7F0] px-4 py-2 text-[11px] font-medium text-[#5A6278] transition hover:bg-[#F0F2F8]"
                        @click="showSendDialog = false"
                    >
                        Cancel
                    </button>
                    <button
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#059669] px-4 py-2 text-[11px] font-medium text-white transition hover:bg-[#047857] disabled:opacity-50"
                        :disabled="!sendRecipient || isSending"
                        @click="sendReport"
                    >
                        <Send class="h-3.5 w-3.5" />
                        {{ isSending ? 'Sending…' : 'Send' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
