/*
 * Emoji picker: a 😊 button that opens a small panel of emoji and inserts the chosen one at the cursor.
 *   <textarea data-emoji-picker>   gets a button below it
 *   <trix-editor>                  gets a button in its toolbar (load this script before Trix)
 * The emoji are plain text (utf8mb4 in the database), so they need nothing special to save or show.
 */
(function () {
    const GROUPS = {
        'Smileys': '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇 🙂 🙃 😉 😌 😍 🥰 😘 😋 😛 😜 🤪 😎 🤩 🥳 😏 😒 😞 😔 😟 😕 🙁 😣 😖 😫 😩 🥺 😢 😭 😤 😠 😡 🤯 😳 🥵 🥶 😱 😨 😰 😥 🤗 🤔 🤭 🤫 😶 😐 😑 😬 🙄 😯 😴 🤤 😪 😵 🤐 🥴 🤢 🤮 🤧 😷 🤒 🤕',
        'Gebaren': '👍 👎 👌 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 👋 👏 🙌 👐 🤲 🙏 💪 🦵 🦶 👀 🧠 ❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💔 💯 🔥 ✨ ⭐ 🌟 💥 💦 💨',
        'Sport': '🏃 🏃‍♂️ 🏃‍♀️ 🚴 🏊 🤾 ⚽ 🏀 🏐 🎾 🏆 🥇 🥈 🥉 🏅 🎽 👟 ⏱️ 🚩 🏁 ⛵ 🚣 🧘 🏋️ 🤸 ⛰️ 🏔️ 🗺️ 📍',
        'Natuur': '☀️ 🌤️ ⛅ 🌥️ ☁️ 🌦️ 🌧️ ⛈️ 🌩️ 🌨️ ❄️ ⛄ 🌈 🌊 💧 🌙 🌍 🌲 🌳 🍂 🍁 🌸 🌻 🌷 🐶 🐱 🐴 🐮 🐷 🐔 🦆 🐟 🐞 🦋',
        'Eten': '☕ 🍵 🍺 🍻 🥂 🍷 🥤 💧 🍕 🍔 🍟 🌭 🥪 🌮 🍝 🍜 🍣 🥗 🍎 🍌 🍓 🍇 🍉 🥑 🥕 🍞 🥐 🧀 🥚 🍳 🍰 🎂 🍪 🍫 🍦 🍩',
        'Overig': '🎉 🎊 🎈 🎁 🎄 🎃 📅 📷 🎵 🎶 🎮 📚 ✏️ 📝 💡 🔑 🏠 🚗 🚲 ✈️ 🚀 ⏰ ✅ ❌ ⚠️ ❓ ❗ ➡️ ⬅️ ⬆️ ⬇️ 🇳🇱',
    };

    let panel = null;
    let insert = null;

    function buildPanel() {
        panel = document.createElement('div');
        panel.className = 'emoji-picker-panel shadow';
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Emoji kiezen');
        for (const [name, list] of Object.entries(GROUPS)) {
            const heading = document.createElement('div');
            heading.className = 'emoji-picker-heading';
            heading.textContent = name;
            panel.appendChild(heading);
            const grid = document.createElement('div');
            grid.className = 'emoji-picker-grid';
            for (const emoji of list.split(' ')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'emoji-picker-emoji';
                button.textContent = emoji;
                button.title = emoji;
                grid.appendChild(button);
            }
            panel.appendChild(grid);
        }
        // Keep the focus (and the cursor) in the text while clicking in the panel
        panel.addEventListener('mousedown', event => event.preventDefault());
        panel.addEventListener('click', function (event) {
            const button = event.target.closest('.emoji-picker-emoji');
            if (button && insert) {
                insert(button.textContent);
                close();
            }
        });
        document.body.appendChild(panel);

        document.addEventListener('mousedown', function (event) {
            if (!panel.hidden && !panel.contains(event.target) && !event.target.closest('.emoji-picker-toggle')) {
                close();
            }
        });
        document.addEventListener('keydown', event => event.key === 'Escape' && close());
        window.addEventListener('resize', close);
    }

    function open(toggle, insertFunction) {
        if (!panel) {
            buildPanel();
        }
        if (!panel.hidden && insert === insertFunction) {
            close();
            return;
        }
        insert = insertFunction;
        panel.hidden = false;
        const rect = toggle.getBoundingClientRect();
        const left = Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - panel.offsetWidth - 8);
        panel.style.left = Math.max(8, left) + 'px';
        panel.style.top = (rect.bottom + window.scrollY + 4) + 'px';
    }

    function close() {
        if (panel) {
            panel.hidden = true;
        }
        insert = null;
    }

    function toggleButton(className) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'emoji-picker-toggle ' + className;
        button.title = 'Emoji invoegen';
        button.setAttribute('aria-label', 'Emoji invoegen');
        button.textContent = '😊';
        button.addEventListener('mousedown', event => event.preventDefault());
        return button;
    }

    // Plain text fields
    function attachTextarea(field) {
        const button = toggleButton('btn btn-sm btn-outline-secondary mt-1');
        button.insertAdjacentText('beforeend', ' Emoji');
        field.insertAdjacentElement('afterend', button);
        const insertEmoji = function (emoji) {
            const start = field.selectionStart ?? field.value.length;
            const end = field.selectionEnd ?? field.value.length;
            field.setRangeText(emoji, start, end, 'end');
            field.focus();
            field.dispatchEvent(new Event('input', { bubbles: true }));
        };
        button.addEventListener('click', () => open(button, insertEmoji));
    }

    // Trix editor: a button in its toolbar
    function attachTrix(editorElement) {
        if (editorElement.dataset.emojiPicker === 'ready' || !editorElement.toolbarElement) {
            return;
        }
        editorElement.dataset.emojiPicker = 'ready';
        const group = document.createElement('span');
        group.className = 'trix-button-group';
        const button = toggleButton('trix-button');
        group.appendChild(button);
        editorElement.toolbarElement.querySelector('.trix-button-row').appendChild(group);

        const insertEmoji = function (emoji) {
            editorElement.focus();
            editorElement.editor.insertString(emoji);
        };
        button.addEventListener('click', () => open(button, insertEmoji));
    }

    document.addEventListener('trix-initialize', event => attachTrix(event.target));
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('textarea[data-emoji-picker]').forEach(attachTextarea);
        document.querySelectorAll('trix-editor').forEach(editor => editor.editor && attachTrix(editor));
    });
})();
