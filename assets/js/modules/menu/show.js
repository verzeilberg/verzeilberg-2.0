/*
 * Menu detail (admin/menu/show): drag & drop to reorder the items (SortableJS), also into and out of submenus.
 * After a drop the whole order of the list(s) involved is saved, so every item gets a position of its own.
 * Script tag config: data-reorder-url, data-csrf-token. Deleting an item uses components/ajax-delete.js.
 */
(function () {
    const config = document.currentScript.dataset;

    // The items directly in a list, in order, and the item the list is the submenu of (null = top level)
    function describe(list) {
        const owner = list.closest('.sortable-menu-item');
        return {
            parentId: owner ? Number(owner.dataset.itemId) : null,
            items: itemsOf(list).map(li => Number(li.dataset.itemId)),
        };
    }

    function itemsOf(list) {
        return Array.from(list.children).filter(li => li.matches('.sortable-menu-item'));
    }

    // The "Order: n" labels, after a successful save
    function showPositions(list) {
        itemsOf(list).forEach((li, index) => {
            const label = li.querySelector(':scope > div .js-order');
            if (label) {
                label.textContent = index + 1;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sortable-menu').forEach(list => {
            new Sortable(list, {
                group: 'menu-' + list.dataset.menuId,
                animation: 150,
                handle: '.drag-handle',
                onEnd: function (event) {
                    if (event.from === event.to && event.oldIndex === event.newIndex) {
                        return;
                    }
                    const lists = event.from === event.to ? [event.to] : [event.from, event.to];

                    fetch(config.reorderUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        body: JSON.stringify({_token: config.csrfToken, lists: lists.map(describe)}),
                    })
                        .then(response => response.json().catch(() => ({})).then(data => {
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Opslaan mislukt.');
                            }
                            lists.forEach(showPositions);
                        }))
                        .catch(error => {
                            // Show what is really saved instead of an order that isn't
                            AppDialog.alert('De nieuwe volgorde is niet opgeslagen: ' + error.message + ' De pagina wordt opnieuw geladen.')
                                .then(() => location.reload());
                        });
                },
            });
        });
    });
})();
