/*
 * The admin's JavaScript.
 *
 * **Shipped already built.** Nobody using this touches npm.
 * Livewire swaps the DOM out, so anything we have had a hand in lives inside
 * wire:ignore and the values pass through a hidden textarea.
 */

/*
 * The code editor is fetched only where one is wanted.
 *
 * **It is nine tenths of this file's weight** and most screens never open a
 * code pill, so it lives in a bundle of its own and arrives the first time a
 * page carries a textarea asking for it. Everything here waits on the same
 * promise, so a page with six editors fetches it once.
 */
let editorArriving = null

function editorBundle() {
    if (window.bwEditor) return Promise.resolve(window.bwEditor)

    if (editorArriving) return editorArriving

    const src = document.querySelector('script[data-bw-editor]')?.dataset.bwEditor

    if (!src) return Promise.resolve(null)

    editorArriving = new Promise((resolve) => {
        const script = document.createElement('script')
        script.src = src
        script.onload = () => resolve(window.bwEditor ?? null)
        // **A failed fetch leaves a plain textarea**, which still types and
        // still saves. The screen is poorer, never broken.
        script.onerror = () => resolve(null)
        document.head.appendChild(script)
    })

    return editorArriving
}

function mountEditor(textarea) {
    if (textarea.bwView || textarea.bwArriving) return

    textarea.bwArriving = true

    editorBundle().then((editor) => {
        textarea.bwArriving = false
        editor?.mount(textarea)
    })
}

function refreshEditors() {
    window.bwEditor?.refresh()
}

/* ------------------------------------------------------------------ */

/** Wrap the selection in marks (bold, a link, and so on). */
function surround(textarea, before, after, placeholder) {
    const { selectionStart: start, selectionEnd: end, value } = textarea
    const selected = value.slice(start, end) || placeholder

    textarea.value = value.slice(0, start) + before + selected + after + value.slice(end)
    textarea.focus()
    textarea.setSelectionRange(start + before.length, start + before.length + selected.length)
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
}

/** Put a mark at the start of the lines (a heading, a list). */
function prefixLines(textarea, prefix) {
    const { selectionStart: start, selectionEnd: end, value } = textarea
    const from = value.lastIndexOf('\n', start - 1) + 1
    const to = value.indexOf('\n', end) === -1 ? value.length : value.indexOf('\n', end)
    const lines = value.slice(from, to).split('\n').map((line) => (line.startsWith(prefix) ? line : prefix + line))

    textarea.value = value.slice(0, from) + lines.join('\n') + value.slice(to)
    textarea.focus()
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
}

const TOOLS = [
    { label: 'B', title: 'Bold', run: (t) => surround(t, '**', '**', 'bold text') },
    { label: 'I', title: 'Italic', run: (t) => surround(t, '*', '*', 'italic text') },
    { label: 'Heading', title: 'Heading', run: (t) => prefixLines(t, '## ') },
    { label: 'List', title: 'Bulleted list', run: (t) => prefixLines(t, '- ') },
    { label: 'Link', title: 'Link', run: (t) => surround(t, '[', '](https://)', 'link text') },
]

const BUTTON = 'cursor-pointer rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs font-semibold '
    + 'hover:border-bw-accent dark:border-gray-700 dark:bg-gray-900'

/** Give a Markdown field a small toolbar. **Nobody has to know the syntax.** */
function mountMarkdownTools(textarea) {
    if (textarea.dataset.bwMounted) return

    textarea.dataset.bwMounted = '1'

    const bar = document.createElement('div')
    bar.className = 'mb-1 flex flex-wrap gap-1'

    TOOLS.forEach((tool) => {
        const button = document.createElement('button')
        button.type = 'button'
        button.className = BUTTON
        button.textContent = tool.label
        button.title = tool.title
        button.addEventListener('click', () => tool.run(textarea))
        bar.appendChild(button)
    })

    textarea.parentNode.insertBefore(bar, textarea)
}

/* ------------------------------------------------------------------ */

/**
 * Dragging blocks about.
 *
 * **Among siblings only.** Dropping onto another parent involves re-hanging
 * the nesting and breaks easily, so for now it is up and down — the same as
 * the ↑↓ buttons.
 */
function mountDragging(root) {
    let dragged = null
    let pointerY = 0
    let scrolling = false

    // On the arranging screen too, going near the edge scrolls (as in the preview)
    const autoScroll = () => {
        if (scrolling) return

        scrolling = true

        const step = () => {
            if (!dragged) {
                scrolling = false

                return
            }

            const height = window.innerHeight
            const edge = Math.min(140, height / 4)
            let delta = 0

            if (pointerY < edge) {
                delta = -Math.ceil((edge - pointerY) / 3)
            } else if (pointerY > height - edge) {
                delta = Math.ceil((pointerY - (height - edge)) / 3)
            }

            if (delta !== 0) window.scrollBy(0, delta)

            requestAnimationFrame(step)
        }

        requestAnimationFrame(step)
    }

    root.addEventListener('dragstart', (event) => {
        const node = event.target.closest('[data-bw-path]')
        if (!node) return

        dragged = node
        node.classList.add('bw-dragging')
        event.dataTransfer.effectAllowed = 'move'
    })

    root.addEventListener('dragend', () => {
        if (dragged) dragged.classList.remove('bw-dragging')
        root.querySelectorAll('.bw-drop-target').forEach((el) => el.classList.remove('bw-drop-target'))
        dragged = null
    })

    root.addEventListener('dragover', (event) => {
        if (dragged) {
            pointerY = event.clientY
            autoScroll()
        }

        // The arranging screen has places to drop. **Across columns too.**
        const zone = event.target.closest('[data-bw-drop]')

        if (zone && dragged) {
            event.preventDefault()
            zone.classList.add('bw-drop-zone-active')

            return
        }

        const node = event.target.closest('[data-bw-path]')
        if (!node || !dragged || node === dragged) return
        if (parentOf(node) !== parentOf(dragged)) return

        event.preventDefault()
        node.classList.add('bw-drop-target')
    })

    root.addEventListener('dragleave', (event) => {
        const zone = event.target.closest('[data-bw-drop]')
        if (zone) zone.classList.remove('bw-drop-zone-active')

        const node = event.target.closest('[data-bw-path]')
        if (node) node.classList.remove('bw-drop-target')
    })

    root.addEventListener('drop', (event) => {
        const zone = event.target.closest('[data-bw-drop]')

        if (zone && dragged) {
            event.preventDefault()
            zone.classList.remove('bw-drop-zone-active')

            const [parent, index] = zone.dataset.bwDrop.split(':')

            call(zone, 'moveInto', dragged.dataset.bwPath, parent, Number(index))

            return
        }

        const node = event.target.closest('[data-bw-path]')
        if (!node || !dragged || node === dragged) return
        if (parentOf(node) !== parentOf(dragged)) return

        event.preventDefault()
        node.classList.remove('bw-drop-target')

        call(node, 'moveTo', dragged.dataset.bwPath, node.dataset.bwPath)
    })
}

/** Tell the Livewire component that owns the element. */
function call(element, method, ...args) {
    const component = element.closest('[wire\\:id]')

    if (component) {
        window.Livewire.find(component.getAttribute('wire:id')).call(method, ...args)
    }
}

/** The parent of "0.1.2" is "0.1". Moving stays within one parent. */
function parentOf(node) {
    const path = node.dataset.bwPath || ''

    return path.includes('.') ? path.slice(0, path.lastIndexOf('.')) : ''
}

/* ------------------------------------------------------------------ */

/**
 * A file that is too big is stopped before it is sent.
 *
 * **Over PHP's limit the whole request is thrown away.** Nothing reaches the
 * server, so this is the only place it can be said.
 */
function mountUploadGuard(input) {
    if (input.dataset.bwMounted) return

    input.dataset.bwMounted = '1'

    input.addEventListener('change', () => {
        const max = Number(input.dataset.bwMaxBytes || 0)
        const tooBig = Array.from(input.files || []).filter((file) => max > 0 && file.size > max)

        if (tooBig.length === 0) return

        const names = tooBig.map((file) => file.name).join('、')

        window.alert(`${names} is too big. Each file can be up to ${input.dataset.bwMaxLabel}.`)
        input.value = ''
        input.dispatchEvent(new Event('change', { bubbles: true }))
    })
}

/**
 * When something is moved in the preview, this side rewrites it.
 *
 * **Inside the iframe is the real site.** None of the admin's machinery goes
 * in there; only word of what happened comes back, and is handed to Livewire.
 */
function mountBuilderBridge() {
    if (window.bwBridgeMounted) return

    window.bwBridgeMounted = true

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || event.data?.source !== 'bladewright') return

        const frame = document.querySelector('iframe[data-bw-builder]')
        if (!frame) return

        if (event.data.type === 'move') {
            call(frame, 'moveInto', event.data.from, event.data.parent, event.data.index)
        }

        // Dropped from the toolbox. **It goes exactly where it landed.**
        if (event.data.type === 'insert') {
            call(frame, 'insertAt', event.data.block, event.data.path, event.data.after !== false)
        }

        if (event.data.type === 'add') {
            call(frame, 'openPicker', event.data.path, event.data.after !== false)
        }

        if (event.data.type === 'text') {
            call(frame, 'updateText', event.data.path, event.data.field, event.data.value)
        }

        // Once the tools inside wake up, tell them which of light or dark is chosen
        if (event.data.type === 'ready') {
            applyScheme(chosenScheme())
        }

        if (event.data.type === 'select') {
            call(frame, 'select', event.data.path)
        }
    })
}

const TOAST_TONES = {
    ok: 'border-green-200 bg-white text-green-900 dark:border-green-900 dark:bg-gray-900 dark:text-green-100',
    error: 'border-red-200 bg-white text-red-900 dark:border-red-900 dark:bg-gray-900 dark:text-red-100',
}

/**
 * Float the message.
 *
 * **The page's contents are not pushed down.** Back when it was a band, every
 * save shifted the table and moved whatever you were about to press.
 */
function toast(message, tone = 'ok') {
    const host = document.getElementById('bw-toasts')
    if (!host) return

    const item = document.createElement('div')

    item.className = 'pointer-events-auto flex max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg '
        + 'transition duration-200 translate-y-2 opacity-0 ' + (TOAST_TONES[tone] ?? TOAST_TONES.ok)
    item.textContent = message
    host.appendChild(item)

    requestAnimationFrame(() => item.classList.remove('translate-y-2', 'opacity-0'))

    const remove = () => {
        item.classList.add('translate-y-2', 'opacity-0')
        setTimeout(() => item.remove(), 200)
    }

    item.addEventListener('click', remove)
    setTimeout(remove, tone === 'error' ? 8000 : 4000)
}

/**
 * The drawer on the right, the little window on top, and copying a URL.
 *
 * **It moves the moment it is pressed.** Waiting for the server before it
 * slides looks like a beat's hesitation (the state itself is the server's).
 */
/**
 * Run something once Livewire is there.
 *
 * **The event may already have fired.** Anything registered from
 * `DOMContentLoaded` is too late for `livewire:initialized`, and a listener
 * added after it has gone off simply never runs — which is how the little
 * window stopped closing after a folder was made, while the toast that came
 * with it still appeared (that one is registered as the file loads).
 */
function whenLivewireReady(work) {
    if (window.Livewire) {
        work()

        return
    }

    document.addEventListener('livewire:initialized', work, { once: true })
}

function mountOverlays() {
    if (window.bwOverlaysMounted) return

    window.bwOverlaysMounted = true

    const drawer = () => document.querySelector('[data-bw-drawer]')

    const setDrawer = (open) => {
        const panel = drawer()
        if (!panel) return

        panel.classList.toggle('translate-x-full', !open)
        panel.setAttribute('aria-hidden', open ? 'false' : 'true')
    }

    const setModal = (name, open) => {
        const box = document.querySelector(`[data-bw-modal="${name}"]`)
        if (!box) return

        box.classList.toggle('hidden', !open)
        box.classList.toggle('flex', open)

        if (open) box.querySelector('[data-bw-modal-focus]')?.focus()
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-bw-drawer-open]')) setDrawer(true)
        if (event.target.closest('[data-bw-drawer-close]')) setDrawer(false)

        const opener = event.target.closest('[data-bw-modal-open]')
        if (opener) setModal(opener.dataset.bwModalOpen, true)

        const closer = event.target.closest('[data-bw-modal-close]')
        if (closer) setModal(closer.closest('[data-bw-modal]')?.dataset.bwModal, false)

        const copier = event.target.closest('[data-bw-copy]')
        if (copier) copy(copier.dataset.bwCopy)
    })

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return

        // Count it as the close button being pressed. **The server's state closes with it.**
        document.querySelector('[data-bw-modal]:not(.hidden) [data-bw-modal-close]')?.click()
        document.querySelector('[data-bw-drawer]:not(.translate-x-full) [data-bw-drawer-close]')?.click()
    })

    // **The server says when it is done**, and the little window closes then —
    // not when the button was pressed, which would close it over a failure.
    whenLivewireReady(() => {
        window.Livewire.on('bw-close-modal', (event) => {
            const payload = Array.isArray(event) ? event[0] : event

            setModal(payload?.name, false)
        })
    })
}

/** Copy a URL. **It always says so, so the press is felt.** */
async function copy(text) {
    if (!text) return

    try {
        await navigator.clipboard.writeText(text)
        toast('Copied.')
    } catch (e) {
        // Over plain http the clipboard is unavailable in some browsers. Then it is selected by hand.
        toast('Could not copy. Select the URL and copy it yourself.', 'error')
    }
}

/**
 * Let a tile from the toolbox be carried into the preview.
 *
 * **It crosses the iframe, and the same origin means the label arrives
 * intact.** The tools inside (builder.js) decide where it landed and send that
 * back.
 */
/**
 * A whole row opens what it is a row of.
 *
 * **The row is the thing, not the words in it.** A list where only the name is
 * live makes people aim at a few characters, and everywhere else on the row —
 * the state, the date, the empty space to the right — does nothing when
 * pressed, which reads as broken rather than as restraint.
 *
 * The name stays a real link, so it is still reachable by keyboard and still
 * opens in a new tab. This only widens where the mouse may land, and it keeps
 * its hands off anything that was already something to press.
 */
function mountRowLinks() {
    if (window.bwRowLinksMounted) return

    window.bwRowLinksMounted = true

    document.addEventListener('click', (event) => {
        const row = event.target.closest('[data-bw-row-href]')
        if (!row) return

        // Whatever was already an action stays that action.
        if (event.target.closest('a, button, input, select, textarea, label')) return

        // A selection made by dragging across the row is not a press.
        if (window.getSelection()?.toString()) return

        window.location.href = row.dataset.bwRowHref
    })

    /*
     * **The keyboard reaches the row too.**
     *
     * The name used to be a link, so Tab found it; now that the row is the
     * whole of it, the row is what Tab has to find. `data-bw-row` marks one,
     * and Enter presses it — as a click, so a row that goes somewhere and a row
     * that asks Livewire to do something both answer without knowing which
     * they are.
     */
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return

        const row = event.target.closest?.('[data-bw-row]')

        // Only when the row itself holds the focus. Inside it, whatever is
        // focused answers Enter in its own way.
        if (!row || row !== event.target) return

        event.preventDefault()
        row.click()
    })
}

function mountToolDrag() {
    if (window.bwToolDragMounted) return

    window.bwToolDragMounted = true

    document.addEventListener('dragstart', (event) => {
        const tile = event.target.closest?.('[data-bw-tool]')
        if (!tile) return

        event.dataTransfer.setData('text/bw-block', tile.dataset.bwTool)
        event.dataTransfer.effectAllowed = 'copy'
    })
}

/** Opening and closing the column. Remembered, so nobody reopens it on every screen. */
function mountSidebarToggle() {
    if (window.bwSidebarMounted) return

    window.bwSidebarMounted = true

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-bw-sidebar-toggle]')) return

        const root = document.documentElement
        const next = root.dataset.bwSidebar === 'closed' ? 'open' : 'closed'

        root.dataset.bwSidebar = next

        try {
            localStorage.setItem('bw-sidebar', next)
        } catch (e) {
            // Even when it cannot be remembered, opening and closing still works.
        }
    })
}

/**
 * Show what is typed on the workbench in the preview as it goes.
 *
 * **Nothing is saved.** A revision per keystroke would bury the history, so
 * the look keeps up and Save settles it.
 */
function mountInspectorSync() {
    if (window.bwSyncMounted) return

    window.bwSyncMounted = true

    document.addEventListener('input', (event) => {
        const field = event.target.closest('[data-bw-sync-field]')
        if (!field) return

        const frame = document.querySelector('iframe[data-bw-builder]')

        frame?.contentWindow?.postMessage({
            source: 'bladewright',
            type: 'preview',
            path: field.dataset.bwSyncPath,
            field: field.dataset.bwSyncField,
            value: field.value,
        }, window.location.origin)
    })
}

/**
 * The preview that can be pressed.
 *
 * **Inside the iframe is the real page**, same-origin, every block stamped
 * with its uuid (`data-bw-block`, only on the editing preview). This side
 * reaches in the way the scheme does: hover draws the outline, one press
 * opens the block's cards beside the preview, two presses let the words be
 * typed where they stand — Enter or leaving keeps them, Escape puts them
 * back. Nothing of the admin's machinery is installed in there.
 */
function mountEditablePreviews() {
    if (window.bwEditableMounted) return

    window.bwEditableMounted = true

    // **A listener on the frame itself** — an iframe's load does not reach
    // the parent document — hooked once per element, and hooked again for
    // whatever Livewire morphs in. Every reload is a new document inside,
    // so wiring is guarded per document, not per frame.
    const bind = () => {
        document.querySelectorAll('iframe[data-bw-editable]').forEach((frame) => {
            if (!frame.bwEditableHooked) {
                frame.bwEditableHooked = true
                frame.addEventListener('load', () => wireEditablePreview(frame))
            }

            // Already loaded before this ran (a fast page, a morph).
            wireEditablePreview(frame)
        })
    }

    bind()
    document.addEventListener('livewire:navigated', bind)
    whenLivewireReady(() => window.Livewire.hook('morph.updated', bind))

    // **And a watchman, because the moments are slippery**: a morph can
    // swap the element between hooks, srcdoc parses on its own clock, and
    // a load can fire before any listener stands. Wiring is guarded per
    // document, so looking twice costs nothing.
    setInterval(bind, 500)
}

function wireEditablePreview(frame) {
    const doc = frame.contentDocument

    // **Not before the document can hold tools.** A srcdoc frame hands out
    // its document mid-parse, body still null — wiring then would throw
    // halfway with the guard flag already up, and the preview would stand
    // dead for good. The load listener and the watchman both come back.
    if (!doc || doc.bwEditableWired || !doc.body || doc.readyState === 'loading') return

    doc.bwEditableWired = true

    const style = doc.createElement('style')
    style.textContent = '[data-bw-block]>*,[data-bw-component]>*{cursor:pointer}'
        + '.bw-picked{outline:2px solid #3538cd;outline-offset:2px;border-radius:2px}'
        + '.bw-picked-part{outline:2px dashed #3538cd;outline-offset:-2px;border-radius:2px}'
        + '[contenteditable="true"]{outline:2px solid #12b76a;outline-offset:2px;cursor:text}'
    doc.head?.appendChild(style)

    // **Never `instanceof` across the frame boundary**: the iframe's
    // elements belong to their own window's classes, and a cross-realm
    // instanceof is always false. Asked for the method instead.
    //
    // **The innermost stamp wins**: on the words, that is their block; on
    // the space around them, their component — the two layers, told apart
    // by where the press landed.
    const pickOf = (target) => typeof target?.closest === 'function'
        ? target.closest('[data-bw-block],[data-bw-component]')
        : null
    const blockOf = (target) => typeof target?.closest === 'function' ? target.closest('[data-bw-block]') : null

    let lit = null

    const wire = () => {
        const component = frame.closest('[wire\\:id]')

        return component ? window.Livewire.find(component.getAttribute('wire:id')) : null
    }

    // **The shelf a + opens.** The spot above or below the band parts, and
    // every placeable component slides in as a miniature — rendered through
    // the real renderer inside this very document, so each one wears the
    // site's own CSS. Pressing one puts it right there.
    let shelf = null

    const closeShelf = () => { shelf?.remove(); shelf = null }

    const openShelf = async (aimed, below) => {
        closeShelf()
        hidePlus()

        const wrapper = aimed.wrapper
        if (!wrapper || !wrapper.isConnected) return

        shelf = doc.createElement('div')
        shelf.setAttribute('data-bw-shelf', '')
        shelf.style.cssText = 'box-sizing:border-box;margin:0;padding:14px 16px;background:rgba(53,56,205,.05);'
            + 'border-block:2px dashed #3538cd;overflow:hidden;max-height:0;transition:max-height .25s ease;'
            // In a grid or row component the shelf takes the whole line,
            // not a cell.
            + 'grid-column:1/-1;flex-basis:100%'

        const head = doc.createElement('div')
        head.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:10px;font:600 13px ui-sans-serif,system-ui;color:#3538cd'
        head.textContent = below ? '↓' : '↑'

        const shut = doc.createElement('button')
        shut.type = 'button'
        shut.textContent = '×'
        shut.style.cssText = 'margin-left:auto;border:0;background:transparent;color:#3538cd;font-size:18px;cursor:pointer;line-height:1'
        shut.addEventListener('click', () => closeShelf())
        head.appendChild(shut)

        const strip = doc.createElement('div')
        strip.style.cssText = 'display:flex;gap:12px;overflow-x:auto;padding-bottom:6px'
        strip.innerHTML = '<div style="font:13px ui-sans-serif,system-ui;color:#667085">…</div>'
        strip.addEventListener('click', (event) => {
            const mini = typeof event.target?.closest === 'function' ? event.target.closest('[data-bw-mini]') : null

            if (!mini) return

            aimed.kind === 'block'
                ? wire()?.call('placeBlock', aimed.comp, mini.dataset.bwMini, aimed.at, below)
                : wire()?.call('placeComponent', mini.dataset.bwMini, aimed.slot, below)

            closeShelf()
        })

        shelf.append(head, strip)
        below ? wrapper.after(shelf) : wrapper.before(shelf)
        requestAnimationFrame(() => { shelf.style.maxHeight = '260px' })
        shelf.scrollIntoView({ block: 'nearest', behavior: 'smooth' })

        const tiles = await wire()?.call(aimed.kind === 'block' ? 'blockShelf' : 'componentShelf')
        if (shelf && typeof tiles === 'string') strip.innerHTML = tiles
    }

    doc.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeShelf() })

    // **The + buttons above and below a band.** Only the page's own rows
    // carry a slot, so only they get them — a band worn by the layout, or a
    // component inside another, offers no place to put things around.
    const plus = (below) => {
        const button = doc.createElement('button')
        button.type = 'button'
        button.textContent = '+'
        button.setAttribute('data-bw-plus', below ? 'below' : 'above')
        button.style.cssText = 'position:absolute;display:none;z-index:2147483000;width:28px;height:28px;'
            + 'margin-left:-14px;border-radius:9999px;border:0;background:#3538cd;color:#fff;'
            + 'font-size:18px;line-height:28px;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.25)'
        button.addEventListener('click', (event) => {
            event.preventDefault()
            event.stopPropagation()

            if (button.bwAim) openShelf(button.bwAim, below)
        })
        doc.body.appendChild(button)

        return button
    }

    const above = plus(false)
    const below = plus(true)

    // **The × in the frame's top-right corner** takes the thing off the
    // page — off, not gone: what it was stays on the shelf.
    const takeOff = doc.createElement('button')
    takeOff.type = 'button'
    takeOff.textContent = '×'
    takeOff.setAttribute('data-bw-plus', 'x')
    takeOff.style.cssText = 'position:absolute;display:none;z-index:2147483000;width:22px;height:22px;'
        + 'border-radius:9999px;border:1px solid #fda29b;background:#fff;color:#d92d20;'
        + 'font-size:14px;line-height:20px;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.2);padding:0'
    takeOff.addEventListener('click', (event) => {
        event.preventDefault()
        event.stopPropagation()

        const aim = takeOff.bwAim
        if (!aim) return

        aim.kind === 'block'
            ? call(frame, 'removeBlockAt', aim.comp, aim.at)
            : call(frame, 'removeSlot', aim.slot)

        hidePlus()
    })
    doc.body.appendChild(takeOff)

    // **The grip that moves a band.** It floats at the band's left edge;
    // taking hold of it and letting go over another band puts the one in
    // the other's place — the same drag the Structure tree speaks.
    const grip = doc.createElement('div')
    grip.setAttribute('data-bw-plus', 'grip')
    grip.textContent = '⋮⋮'
    grip.style.cssText = 'position:absolute;display:none;z-index:2147483000;width:22px;height:28px;'
        + 'border-radius:6px;background:#3538cd;color:#fff;font-size:13px;line-height:28px;'
        + 'text-align:center;letter-spacing:-2px;cursor:grab;box-shadow:0 1px 4px rgba(0,0,0,.25);user-select:none'
    doc.body.appendChild(grip)

    // The line that says where it will land.
    const seam = doc.createElement('div')
    seam.style.cssText = 'position:absolute;display:none;z-index:2147483000;height:3px;background:#3538cd;border-radius:2px;pointer-events:none'
    doc.body.appendChild(seam)

    // **The name on the outline's shoulder.** A small chip at the top-left
    // says whose frame is lit — filled for a block, dashed for a component,
    // the same words the outlines speak.
    const tag = doc.createElement('div')
    tag.style.cssText = 'position:absolute;display:none;z-index:2147483000;padding:1px 7px;'
        + 'font:600 11px ui-monospace,monospace;border-radius:4px 4px 4px 0;pointer-events:none;white-space:nowrap'
    doc.body.appendChild(tag)

    const hideTag = () => { tag.style.display = 'none' }

    const showTag = (pick) => {
        const box = pick.firstElementChild
        if (!box || !pick.dataset.bwName) { hideTag(); return }

        const rect = box.getBoundingClientRect()
        const isBlock = !!pick.dataset.bwBlock

        tag.textContent = pick.dataset.bwName
        tag.style.background = isBlock ? '#3538cd' : '#fff'
        tag.style.color = isBlock ? '#fff' : '#3538cd'
        tag.style.border = isBlock ? '0' : '1px dashed #3538cd'

        // On the frame's shoulder — and **under its chin when there is no
        // room above** (the header's frames touch the top of the page),
        // never over the words themselves.
        const view = doc.defaultView
        const under = rect.top < 24

        tag.style.borderRadius = under ? '0 4px 4px 4px' : '4px 4px 4px 0'
        tag.style.left = (rect.left + view.scrollX + (isBlock ? -2 : 2)) + 'px'
        tag.style.top = (under ? rect.bottom + view.scrollY + 3 : rect.top + view.scrollY - 19) + 'px'
        tag.style.display = 'block'
    }

    const hidePlus = () => { above.style.display = 'none'; below.style.display = 'none'; grip.style.display = 'none'; takeOff.style.display = 'none' }

    const showPlus = (aimed) => {
        const box = aimed.wrapper.firstElementChild
        if (!box) return

        const view = doc.defaultView
        const rect = box.getBoundingClientRect()
        const x = rect.left + rect.width / 2 + view.scrollX

        above.bwAim = below.bwAim = aimed

        // **In a grid or a row, before and after are left and right** — the
        // siblings stand side by side, so the + stands where the new one
        // would.
        const gridded = aimed.kind === 'block'
            && ['grid', 'flex'].includes(view.getComputedStyle(aimed.wrapper.parentElement).display)

        grip.bwAim = aimed

        // **The grip sits inside the frame's top-left corner.** Outside it
        // the hand would already be in the next layer's land on the way
        // there, and the tools would change under it.
        grip.style.left = (rect.left + view.scrollX + 4) + 'px'
        grip.style.top = (rect.top + view.scrollY + 4) + 'px'
        grip.style.display = 'block'

        // And the × holds the opposite corner, inside for the same reason.
        takeOff.bwAim = aimed
        takeOff.style.left = (rect.right + view.scrollX - 26) + 'px'
        takeOff.style.top = (rect.top + view.scrollY + 4) + 'px'
        takeOff.style.display = 'block'

        if (gridded) {
            const middle = (rect.top + view.scrollY + rect.height / 2 - 14) + 'px'

            above.style.left = (rect.left + view.scrollX) + 'px'
            above.style.top = middle
            below.style.left = (rect.right + view.scrollX) + 'px'
            below.style.top = middle
            above.style.display = below.style.display = 'block'

            return
        }

        above.style.left = below.style.left = x + 'px'
        above.style.top = (rect.top + view.scrollY - 14) + 'px'
        below.style.top = (rect.bottom + view.scrollY - 14) + 'px'
        above.style.display = below.style.display = 'block'
    }

    // Taking hold: from mousedown on the grip to mouseup wherever it lands.
    // **A band moves among the bands; a block moves within its component**
    // — the same rule as everywhere: a row moves only inside its own parent.
    grip.addEventListener('mousedown', (event) => {
        event.preventDefault()
        event.stopPropagation()

        const aim = grip.bwAim
        if (!aim) return

        const from = aim.kind === 'block' ? aim.at : aim.slot
        const view = doc.defaultView
        let target = null
        let hand = null

        // What may be landed on: a band lands among bands; a block lands
        // among its own component's rows and nobody else's.
        const kin = (under) => {
            if (typeof under?.closest !== 'function') return null

            if (aim.kind === 'block') {
                const wrapper = under.closest('[data-bw-at]')

                return wrapper && wrapper.parentElement?.closest('[data-bw-component]')?.dataset.bwComponent === aim.comp
                    ? wrapper
                    : null
            }

            return under.closest('[data-bw-slot]')
        }

        doc.body.style.userSelect = 'none'
        grip.style.cursor = 'grabbing'
        above.style.display = below.style.display = 'none'
        hideTag()

        // **The ghost in the hand.** A shrunken clone of the band rides
        // beside the cursor, and the band itself goes faint — what is being
        // carried, and what is being left, both said at a glance.
        const held = aim.wrapper.firstElementChild
        const ghost = held ? held.cloneNode(true) : null

        if (ghost) {
            ghost.setAttribute('data-bw-ghost', '')
            ghost.style.cssText += ';position:absolute;z-index:2147483001;pointer-events:none;'
                + 'width:' + held.offsetWidth + 'px;transform:scale(' + (aim.kind === 'block' ? '0.4' : '0.22') + ');'
                + 'transform-origin:top left;'
                + 'opacity:.9;box-shadow:0 8px 30px rgba(0,0,0,.35);border-radius:6px;'
                + 'max-height:2000px;overflow:hidden;display:none'
            doc.body.appendChild(ghost)
            held.style.opacity = '.35'
        }

        const carry = () => {
            if (!ghost || !hand) return

            ghost.style.left = (hand.x + view.scrollX + 16) + 'px'
            ghost.style.top = (hand.y + view.scrollY + 16) + 'px'
            ghost.style.display = 'block'
        }

        // Where would it land, were it let go right here? In a grid the
        // seam stands upright — before and after are left and right there.
        const point = () => {
            if (!hand) return

            const wrapper = kin(doc.elementFromPoint(hand.x, hand.y))
            const box = wrapper?.firstElementChild

            if (!box) { seam.style.display = 'none'; target = null; return }

            const rect = box.getBoundingClientRect()
            const gridded = ['grid', 'flex'].includes(view.getComputedStyle(wrapper.parentElement).display)
            const after = gridded
                ? hand.x > rect.left + rect.width / 2
                : hand.y > rect.top + rect.height / 2

            target = { at: Number(wrapper.dataset.bwAt ?? wrapper.dataset.bwSlot), after }

            if (gridded) {
                seam.style.left = ((after ? rect.right : rect.left) + view.scrollX - 1) + 'px'
                seam.style.width = '3px'
                seam.style.height = rect.height + 'px'
                seam.style.top = (rect.top + view.scrollY) + 'px'
            } else {
                seam.style.left = (rect.left + view.scrollX) + 'px'
                seam.style.width = rect.width + 'px'
                seam.style.height = '3px'
                seam.style.top = ((after ? rect.bottom : rect.top) + view.scrollY - 1) + 'px'
            }

            seam.style.display = 'block'
        }

        const follow = (move) => {
            hand = { x: move.clientX, y: move.clientY }
            point()
            carry()
        }

        // **The page scrolls under a held band.** Near either edge the view
        // drifts — faster the deeper in — and the seam keeps aiming while
        // the ground moves under the hand.
        const EDGE = 80
        let drifting = view.requestAnimationFrame(function drift() {
            if (hand) {
                const depth = hand.y > view.innerHeight - EDGE
                    ? hand.y - (view.innerHeight - EDGE)
                    : (hand.y < EDGE ? hand.y - EDGE : 0)

                if (depth !== 0) {
                    view.scrollBy(0, Math.round(depth / 4))
                    point()
                    carry()
                }
            }

            drifting = view.requestAnimationFrame(drift)
        })

        const drop = () => {
            doc.removeEventListener('mousemove', follow)
            doc.removeEventListener('mouseup', drop)
            view.cancelAnimationFrame(drifting)
            doc.body.style.userSelect = ''
            grip.style.cursor = 'grab'
            seam.style.display = 'none'
            ghost?.remove()
            if (held) held.style.opacity = ''
            hidePlus()

            if (target && target.at !== from) {
                aim.kind === 'block'
                    ? call(frame, 'moveBlock', aim.comp, from, target.at, target.after)
                    : call(frame, 'moveSlot', from, target.at, target.after)
            }
        }

        doc.addEventListener('mousemove', follow)
        doc.addEventListener('mouseup', drop)
    })

    doc.addEventListener('mouseover', (event) => {
        // Hovering the + itself keeps everything as it stands.
        if (event.target?.hasAttribute?.('data-bw-plus')) return

        const pick = pickOf(event.target)

        if (lit && lit !== pick) lit.querySelectorAll('.bw-picked,.bw-picked-part').forEach((one) => one.classList.remove('bw-picked', 'bw-picked-part'))

        lit = pick

        // The outline sits on the first real element — the wrapper itself is
        // display:contents and has no box to draw around. A block draws it
        // solid; a component, dashed.
        if (pick && !doc.bwTyping) {
            pick.firstElementChild?.classList.add(pick.dataset.bwBlock ? 'bw-picked' : 'bw-picked-part')
            showTag(pick)
        } else {
            hideTag()
        }

        // The + follows whatever the hand is over: **a block offers block
        // places, a band offers band places** — the innermost stamp wins
        // here too. A block dragged is not a thing, so the grip stays the
        // band's own.
        let aimed = null

        if (pick && pick.dataset.bwBlock && pick.dataset.bwAt) {
            const comp = typeof pick.closest === 'function' ? pick.parentElement?.closest('[data-bw-component]') : null

            if (comp) aimed = { kind: 'block', wrapper: pick, at: Number(pick.dataset.bwAt), comp: comp.dataset.bwComponent }
        } else if (!(pick && pick.dataset.bwBlock)) {
            const slotted = typeof event.target?.closest === 'function' ? event.target.closest('[data-bw-slot]') : null

            if (slotted) aimed = { kind: 'component', wrapper: slotted, slot: Number(slotted.dataset.bwSlot) }
        }

        if (aimed && !doc.bwTyping && !shelf) showPlus(aimed)
        else hidePlus()
    })

    doc.addEventListener('mouseleave', (event) => {
        lit?.querySelectorAll('.bw-picked,.bw-picked-part').forEach((one) => one.classList.remove('bw-picked', 'bw-picked-part'))

        // Only truly leaving the page takes the tools with it — this fires
        // for every element on the way, and the + must not flicker.
        if (event.target === doc.documentElement) {
            hidePlus()
            hideTag()
        }
    }, true)

    // One press opens the cards of whatever layer was pressed. **Nothing
    // of the page's own fires in here** — links do not navigate and a
    // button's onclick does not run: preventDefault stops only default
    // actions, so the press is caught in the capture phase and stopped
    // before it ever reaches the element. The preview is a workbench.
    doc.addEventListener('click', (event) => {
        if (doc.bwTyping) return
        if (event.target?.hasAttribute?.('data-bw-plus')) return

        // The shelf keeps its own presses; a press anywhere else shuts it.
        if (typeof event.target?.closest === 'function' && event.target.closest('[data-bw-shelf]')) return

        if (shelf) {
            event.preventDefault()
            event.stopPropagation()
            closeShelf()

            return
        }

        event.preventDefault()
        event.stopPropagation()

        const pick = pickOf(event.target)
        if (pick) call(frame, 'select', pick.dataset.bwBlock || pick.dataset.bwComponent)
    }, true)

    // Two presses let the words be typed where they stand.
    doc.addEventListener('dblclick', (event) => {
        if (doc.bwTyping) return

        const block = blockOf(event.target)
        if (!block) return

        const spot = typeof event.target?.closest === 'function'
            ? event.target.closest('p,h1,h2,h3,h4,h5,h6,li,td,th,blockquote,figcaption')
            : null

        if (!spot || !block.contains(spot)) return

        event.preventDefault()

        const was = spot.textContent

        doc.bwTyping = true
        spot.classList.remove('bw-picked')
        spot.setAttribute('contenteditable', 'true')
        spot.focus()

        const done = (keep) => {
            spot.removeAttribute('contenteditable')
            doc.bwTyping = false

            if (!keep || spot.textContent === was) {
                spot.textContent = was

                return
            }

            call(frame, 'inlineText', block.dataset.bwBlock, was, spot.textContent)
        }

        spot.addEventListener('blur', () => done(true), { once: true })
        spot.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); spot.blur() }
            if (event.key === 'Escape') { spot.textContent = was; spot.blur() }
        })
    })
}

const DEVICES = {
    desktop: '',
    tablet: '834 × 1112',
    phone: '390 × 844',
}

/**
 * Change the preview's width.
 *
 * **The iframe does not reload.** Only the frame's width changes, so the
 * chosen block and half-typed text both survive. The device chosen is
 * remembered.
 */
function applyDevice(device) {
    document.documentElement.dataset.bwDevice = device

    document.querySelectorAll('.bw-device[data-bw-device]').forEach((button) => {
        button.classList.toggle('is-on', button.dataset.bwDevice === device)
    })

    document.querySelectorAll('.bw-device-size').forEach((label) => {
        const text = DEVICES[device] ?? ''

        // **Writing the same text again still changes the DOM.** The observer
        // reacts, writes again, and round it goes forever (the screen did freeze).
        if (label.textContent !== text) {
            label.textContent = text
        }
    })
}

function chosenDevice() {
    try {
        return localStorage.getItem('bw-device') || 'desktop'
    } catch (e) {
        return 'desktop'
    }
}

/**
 * Switch **the site's** light and dark, inside the preview.
 *
 * Not the admin's palette. The frames follow the visitor's own setting
 * (Pico and Bootstrap both read prefers-color-scheme), so the default here
 * is **auto** — the preview shows what this machine would see — and a press
 * forces one side, by the marks the frameworks read: `data-theme` (Pico),
 * `data-bs-theme` (Bootstrap), and `color-scheme` for everything else.
 */
function applySchemeTo(iframe, scheme) {
    const root = iframe.contentDocument?.documentElement

    if (!root) return

    if (scheme === 'auto') {
        delete root.dataset.theme
        delete root.dataset.bsTheme
        root.style.colorScheme = ''
    } else {
        root.dataset.theme = scheme
        root.dataset.bsTheme = scheme
        root.style.colorScheme = scheme
    }
}

function applyScheme(scheme) {
    document.querySelectorAll('.bw-scheme[data-bw-scheme-set]').forEach((button) => {
        button.classList.toggle('is-on', button.dataset.bwSchemeSet === scheme)
    })

    document.querySelectorAll('.bw-preview-stage iframe').forEach((iframe) => {
        applySchemeTo(iframe, scheme)

        // A preview reloads whenever what it shows changes; the choice rides
        // along.
        if (!iframe.bwSchemeHooked) {
            iframe.bwSchemeHooked = true
            iframe.addEventListener('load', () => applySchemeTo(iframe, chosenScheme()))
        }
    })
}

function chosenScheme() {
    try {
        return localStorage.getItem('bw-scheme') || 'auto'
    } catch (e) {
        return 'auto'
    }
}

function mountSchemeSwitch() {
    if (window.bwSchemeMounted) return

    window.bwSchemeMounted = true

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.bw-scheme[data-bw-scheme-set]')
        if (!button) return

        // Pressing the lit side again lets the preview follow the machine.
        const next = button.classList.contains('is-on') ? 'auto' : button.dataset.bwSchemeSet

        applyScheme(next)

        try {
            localStorage.setItem('bw-scheme', next)
        } catch (e) {
            // Even when it cannot be remembered, the switch still works.
        }
    })
}

/**
 * Light or dark, for **this screen**.
 *
 * Not the site's — that is the layout's own business and lives in the
 * settings. This is the admin, and it belongs to whoever is looking at it, so
 * it is remembered in their browser and nowhere else.
 *
 * **The machine's setting is not followed.** A tool that changes appearance
 * with the time of day is a tool nobody recognises; dark is the default and a
 * choice sticks until it is changed.
 */
function mountThemeToggle() {
    if (window.bwThemeMounted) return

    window.bwThemeMounted = true

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-bw-theme-toggle]')) return

        const root = document.documentElement
        const next = root.dataset.bwTheme === 'dark' ? 'light' : 'dark'

        root.dataset.bwTheme = next

        try {
            localStorage.setItem('bw-theme', next)
        } catch (e) {
            // Even when it cannot be remembered, the switch still works.
        }
    })
}

/**
 * Which face a card is showing — Preview or Code.
 *
 * **The device pills' own way, followed.** The switch happens here and not on
 * the server, so nothing is fetched again, and the pill is remembered in the
 * browser: a reload comes back to what was being worked on.
 */
function applyPills(group, value) {
    document.querySelectorAll(`[data-bw-pills="${group}"][data-bw-pill]`).forEach((button) => {
        button.classList.toggle('is-on', button.dataset.bwPill === value)
    })

    document.querySelectorAll(`[data-bw-pills="${group}"][data-bw-panel]`).forEach((panel) => {
        const showing = panel.dataset.bwPanel === value

        panel.hidden = !showing

        // A code editor built while it was hidden has nothing to measure by.
        if (showing) {
            panel.querySelectorAll('textarea[data-bw-code]').forEach((textarea) => {
                textarea.bwView?.requestMeasure()
            })
        }
    })
}

function chosenPill(group, fallback) {
    try {
        return localStorage.getItem(`bw-pill-${group}`) || fallback
    } catch (e) {
        return fallback
    }
}

/** Every group on the screen, back on the pill it was left on. */
function applyChosenPills() {
    const groups = new Set()

    document.querySelectorAll('[data-bw-pills][data-bw-pill]').forEach((button) => {
        groups.add(button.dataset.bwPills)
    })

    groups.forEach((group) => {
        // The first pill is the one a screen opens on when nothing is remembered.
        const first = document.querySelector(`[data-bw-pills="${group}"][data-bw-pill]`)

        applyPills(group, chosenPill(group, first?.dataset.bwPill))
    })
}

function mountPillSwitch() {
    if (window.bwPillsMounted) return

    window.bwPillsMounted = true

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-bw-pills][data-bw-pill]')
        if (!button) return

        applyPills(button.dataset.bwPills, button.dataset.bwPill)

        try {
            localStorage.setItem(`bw-pill-${button.dataset.bwPills}`, button.dataset.bwPill)
        } catch (e) {
            // Even when it cannot be remembered, the switch still works.
        }
    })

    applyChosenPills()
}

function mountDeviceSwitch() {
    if (window.bwDeviceMounted) return

    window.bwDeviceMounted = true

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.bw-device[data-bw-device]')
        if (!button) return

        applyDevice(button.dataset.bwDevice)

        try {
            localStorage.setItem('bw-device', button.dataset.bwDevice)
        } catch (e) {
            // Even when it cannot be remembered, the switch still works.
        }
    })

    applyDevice(chosenDevice())
}

function mountAll() {
    document.querySelectorAll('textarea[data-bw-code]').forEach(mountEditor)
    refreshEditors()
    document.querySelectorAll('textarea[data-bw-markdown]').forEach(mountMarkdownTools)
    document.querySelectorAll('input[data-bw-max-bytes]').forEach(mountUploadGuard)

    if (document.querySelector('[data-bw-pills]')) applyChosenPills()
    if (document.querySelector('.bw-device')) applyDevice(chosenDevice())
    if (document.querySelector('.bw-scheme')) applyScheme(chosenScheme())
}

/**
 * Applied without fail whenever it appears on the screen.
 *
 * **It does not lean on Livewire's hook names.** Some fields appear only when
 * a tab is switched, and a renamed hook would mean they are never reached
 * again — the code field really did stay a plain textarea.
 */
function watchForNewParts() {
    if (window.bwWatching) return

    window.bwWatching = true

    let queued = false

    const observer = new MutationObserver(() => {
        if (queued) return

        queued = true

        // **It does not watch its own writing.** Applying touches the DOM, and
        // reacting to that would go round and round.
        requestAnimationFrame(() => {
            observer.disconnect()
            mountAll()
            queued = false
            observer.observe(document.body, { childList: true, subtree: true })
        })
    })

    observer.observe(document.body, { childList: true, subtree: true })
}

document.addEventListener('DOMContentLoaded', () => {
    mountAll()
    watchForNewParts()
    mountDragging(document.body)
    mountBuilderBridge()
    mountSidebarToggle()
    mountInspectorSync()
    mountPillSwitch()
    mountDeviceSwitch()
    mountSchemeSwitch()
    mountEditablePreviews()
    mountThemeToggle()
    mountOverlays()
    mountRowLinks()
    mountToolDrag()
})

// Applied again after Livewire re-renders.
document.addEventListener('livewire:navigated', mountAll)
document.addEventListener('livewire:initialized', () => {
    window.Livewire.hook('morph.updated', () => mountAll())

    // The server throws only what to say; how it appears is decided here.
    window.Livewire.on('bw-toast', (event) => {
        const payload = Array.isArray(event) ? event[0] : event

        toast(payload?.message ?? '', payload?.tone ?? 'ok')
    })
})
