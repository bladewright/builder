/*
 * The code editor, on its own.
 *
 * **CodeMirror is nine tenths of the admin's JavaScript**, and most screens
 * never open a code pill at all. So it is built apart and fetched the first
 * time a page actually carries one — the rest of the admin loads without it.
 *
 * What the host bundle needs of it is `window.bwEditor`: one function to
 * mount a textarea, and one to let the mounted ones catch up with the
 * server.
 */

// **basicSetup is not used.** With completion, search and lint it comes to
// 600KB. Taking only what is needed costs less than half of that.
import { EditorState } from '@codemirror/state'
import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter, drawSelection } from '@codemirror/view'
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands'
import { bracketMatching, indentOnInput, syntaxHighlighting, defaultHighlightStyle } from '@codemirror/language'
import { autocompletion, closeBrackets, closeBracketsKeymap, completionKeymap, snippetCompletion } from '@codemirror/autocomplete'
import { html } from '@codemirror/lang-html'
import { css } from '@codemirror/lang-css'
import { oneDark } from '@codemirror/theme-one-dark'
import { Decoration, ViewPlugin } from '@codemirror/view'
import { RangeSetBuilder } from '@codemirror/state'

/**
 * Livewire only receives a value when an input event goes up, and CodeMirror
 * never writes to the textarea, so this bridges the two.
 */
function syncToLivewire(textarea, value) {
    if (textarea.value === value) return

    textarea.value = value
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
}

// Blade is HTML plus PHP. **Markdown does not go into CodeMirror** — carrying
// a whole language definition for it alone adds 100KB, and a small toolbar is
// enough there. CSS is its own, for the style boxes.
function languageFor(textarea) {
    switch (textarea.dataset.bwCode) {
        case 'css':
            return css()
        default:
            return html({ matchClosingTags: true, autoCloseTags: true })
    }
}

/*
 * Colouring Blade.
 *
 * **Parsing it as HTML colours neither {{ }} nor @if** — to HTML they are
 * just text. A thin layer reads the lines and lays marks over them.
 */
const BLADE_PATTERNS = [
    { re: /\{\{--[\s\S]*?--\}\}/g, cls: 'bw-cm-comment' },
    { re: /\{\{[^}]*\}\}|\{!![^}]*!!\}/g, cls: 'bw-cm-echo' },
    { re: /@[a-zA-Z]+/g, cls: 'bw-cm-directive' },
]

const bladeHighlight = ViewPlugin.fromClass(class {
    constructor(view) {
        this.decorations = this.build(view)
    }

    update(update) {
        if (update.docChanged || update.viewportChanged) {
            this.decorations = this.build(update.view)
        }
    }

    build(view) {
        const found = []

        for (const { from, to } of view.visibleRanges) {
            const text = view.state.doc.sliceString(from, to)

            BLADE_PATTERNS.forEach(({ re, cls }) => {
                re.lastIndex = 0

                let match

                while ((match = re.exec(text)) !== null) {
                    found.push({ from: from + match.index, to: from + match.index + match[0].length, cls })
                }
            })
        }

        // **In the order they are laid down.** Out of order, CodeMirror refuses them.
        found.sort((a, b) => a.from - b.from || a.to - b.to)

        const builder = new RangeSetBuilder()
        let last = -1

        found.forEach(({ from, to, cls }) => {
            if (from < last) return

            builder.add(from, to, Decoration.mark({ class: cls }))
            last = to
        })

        return builder.finish()
    }
}, { decorations: (plugin) => plugin.decorations })

/** The things written often, offered as soon as typing starts. */
const SNIPPETS = [
    snippetCompletion('{{-- bw:slot --}}\n{{-- /bw:slot --}}', {
        label: 'bwslot', detail: 'where the child blocks go', type: 'keyword',
    }),
    snippetCompletion('@if (${condition})\n    ${}\n@endif', { label: '@if', type: 'keyword' }),
    snippetCompletion('@foreach (${items} as ${item})\n    ${}\n@endforeach', { label: '@foreach', type: 'keyword' }),
    snippetCompletion('@php\n    ${}\n@endphp', { label: '@php', type: 'keyword' }),
    snippetCompletion('{{ ${value} }}', { label: '{{', detail: 'print a value', type: 'keyword' }),
    snippetCompletion('<livewire:${namespace}::blocks.${key} />', { label: 'livewire', detail: 'call a block', type: 'keyword' }),
]

const DIRECTIVES = [
    '@if', '@elseif', '@else', '@endif', '@foreach', '@endforeach', '@forelse', '@empty', '@endforelse',
    '@for', '@endfor', '@while', '@endwhile', '@php', '@endphp', '@include', '@class', '@checked',
    '@selected', '@auth', '@endauth', '@can', '@endcan', '@bwmarkdown', '@bwslot', '@bwasset',
].map((label) => ({ label, type: 'keyword' }))

/** Offer Blade's own phrasings (HTML's suggestions come from the language). */
function bladeCompletions(context) {
    const word = context.matchBefore(/[@{][a-zA-Z:]*/)

    if (!word || (word.from === word.to && !context.explicit)) {
        return null
    }

    return { from: word.from, options: [...SNIPPETS, ...DIRECTIVES] }
}

/** Swap a code field for CodeMirror. */
function mountEditor(textarea) {
    if (textarea.dataset.bwMounted) return

    textarea.dataset.bwMounted = '1'
    textarea.classList.add('hidden')

    const host = document.createElement('div')
    host.className = 'bw-code-editor mt-5'
    textarea.parentNode.insertBefore(host, textarea.nextSibling)

    const view = new EditorView({
        parent: host,
        state: EditorState.create({
            doc: textarea.value,
            extensions: [
                lineNumbers(),
                highlightActiveLine(),
                highlightActiveLineGutter(),
                drawSelection(),
                history(),
                indentOnInput(),
                bracketMatching(),
                closeBrackets(),
                syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                // Blade's own marks belong on Blade. Plain CSS has a parser
                // that already knows what it is looking at.
                ...(['css'].includes(textarea.dataset.bwCode)
                    ? []
                    : [bladeHighlight, autocompletion({ override: [bladeCompletions], activateOnTyping: true })]),
                keymap.of([...closeBracketsKeymap, ...completionKeymap, ...defaultKeymap, ...historyKeymap, indentWithTab]),
                // A dark admin gets a dark editor, following the palette chosen
                ...(document.documentElement.dataset.bwTheme === 'dark' ? [oneDark] : []),
                languageFor(textarea),
                EditorView.lineWrapping,
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        syncToLivewire(textarea, update.state.doc.toString())
                    }
                }),
            ],
        }),
    })

    // **The card it sits in may be hidden when it is built.** Held onto so
    // the editor can be told to measure itself once it is shown, and so what
    // the server last said can be put in without a reload.
    textarea.bwView = view
    textarea.bwFromServer = serverDoc(textarea)

    // The body can change from the server, as with "go back to this revision".
    textarea.addEventListener('bw:refresh', () => {
        view.dispatch({
            changes: { from: 0, to: view.state.doc.length, insert: textarea.value },
        })
    })
}

/** What the server says the code is, this render. */
function serverDoc(textarea) {
    return textarea.closest('[data-bw-code-doc]')?.dataset.bwCodeDoc ?? null
}

/**
 * Put what the server worked out into the editor. **No reload for it.**
 *
 * CodeMirror is behind `wire:ignore`, so a re-render never reaches it: the
 * code stays as it was mounted while everything around it moves on. This is
 * how it catches up — and **only when the server itself changed**, so nobody
 * is typed over mid-word.
 */
function refreshEditors() {
    document.querySelectorAll('textarea[data-bw-code]').forEach((textarea) => {
        const view = textarea.bwView
        const fromServer = serverDoc(textarea)

        if (!view || fromServer === null || fromServer === textarea.bwFromServer) return

        textarea.bwFromServer = fromServer

        // What is already on the screen is what was asked for: leave it be.
        if (view.state.doc.toString() === fromServer) return

        textarea.value = fromServer

        view.dispatch({
            changes: { from: 0, to: view.state.doc.length, insert: fromServer },
        })
    })
}

// **The one door in.** The host bundle knows nothing of CodeMirror; it asks
// for this file only when a code pill is actually on the page.
window.bwEditor = { mount: mountEditor, refresh: refreshEditors }
