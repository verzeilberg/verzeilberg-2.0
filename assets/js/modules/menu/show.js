/*
 * Menu detail (admin/menu/show): drag & drop to reorder the items (SortableJS), saved per moved item.
 * Script tag config: data-reorder-url with __ID__ for the item id. Deleting an item uses components/ajax-delete.js.
 */
(function () {
    const config = document.currentScript.dataset;

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sortable-menu, .sortable-menu .dropdown-menu').forEach(list => {
            new Sortable(list, {
                group: 'menu-' + list.dataset.menuId,
                animation: 150,
                handle: '.drag-handle',
                onEnd: function (event) {
                    const item = event.item;
                    // Parent item when the item sits in a submenu
                    const parentLi = item.closest('.dropdown-menu')?.closest('.list-group-item');

                    fetch(config.reorderUrl.replace('__ID__', item.dataset.itemId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            sortOrder: event.newIndex + 1,
                            parentId: parentLi ? parentLi.dataset.itemId : ''
                        })
                    });
                }
            });
        });
    });
})();
