/* Checklist edit page (admin/checklist/edit): tabs follow the URL hash, drag & drop ordering of the field tree (jQuery Nestable). */
(function() {
    const config = document.currentScript.dataset;

    // Handle tab activation from URL hash
    function activateTabFromHash() {
        const hash = window.location.hash;
        if (hash) {
            const tab = document.querySelector(`[data-bs-target="${hash}"]`);
            if (tab) {
                new bootstrap.Tab(tab).show();
            }
        }
    }

    // Small Bootstrap toast for feedback on field reordering
    function showToast(message, type) {
        const container = document.getElementById('checklistToasts');
        if (!container) return;
        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center border-0 text-bg-' + (type === 'error' ? 'danger' : 'success');
        toastEl.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toastEl.innerHTML = '<div class="d-flex"><div class="toast-body"></div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Sluiten"></button></div>';
        toastEl.querySelector('.toast-body').textContent = message;
        container.appendChild(toastEl);
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        new bootstrap.Toast(toastEl, {delay: type === 'error' ? 6000 : 2500}).show();
    }

    // Initialize nestable for field reordering (once; the Fields tab can be shown many times)
    let nestableInitialized = false;
    function initNestable() {
        const $tree = $('#field-nestable');
        if (nestableInitialized || !$tree.length || typeof $.fn.nestable === 'undefined') {
            return;
        }
        nestableInitialized = true;

        // Snapshot of the tree just before a drag starts, to restore an invalid or unsaved drop.
        // Capture phase, so it runs before nestable's own mousedown/touchstart handler moves the item.
        let snapshot = null;
        const takeSnapshot = function() { snapshot = $tree.html(); };
        $tree[0].addEventListener('mousedown', takeSnapshot, true);
        $tree[0].addEventListener('touchstart', takeSnapshot, true);
        // Nestable delegates its events to #field-nestable itself, so swapping the inner HTML is safe
        const restoreSnapshot = function() {
            if (snapshot !== null) {
                $tree.html(snapshot);
            }
        };

        $tree.nestable({
            group: 1,
            maxDepth: 3,
            threshold: 20,
            // No collapse/expand buttons: the tree is small enough to always show in full
            expandBtnHTML: '',
            collapseBtnHTML: ''
        }).on('change', function() {
            // Fields can only be nested inside a fieldset
            const invalid = $tree.find('.dd-item').filter(function() {
                const $parent = $(this).parent('.dd-list').closest('.dd-item');
                return $parent.length && $parent.data('field-type') !== 'fieldset';
            });
            if (invalid.length) {
                restoreSnapshot();
                showToast('Velden kunnen alleen in een fieldset geplaatst worden.', 'error');
                return;
            }

            const order = [];
            let seq = 1;
            $tree.find('.dd-item').each(function() {
                const $parent = $(this).parent('.dd-list').closest('.dd-item');
                order.push({
                    id: $(this).data('id'),
                    parent_id: $parent.length ? $parent.data('id') : 0,
                    order: seq++
                });
            });

            $.ajax({
                url: config.reorderUrl,
                method: 'POST',
                headers: {'X-CSRF-Token': config.reorderToken},
                contentType: 'application/json',
                data: JSON.stringify({order: order}),
                dataType: 'json'
            }).done(function(response) {
                if (response && response.success) {
                    showToast('Volgorde opgeslagen.', 'success');
                } else {
                    restoreSnapshot();
                    showToast((response && response.message) || 'De volgorde kon niet worden opgeslagen.', 'error');
                }
            }).fail(function(xhr) {
                restoreSnapshot();
                showToast((xhr.responseJSON && xhr.responseJSON.message) || 'De volgorde kon niet worden opgeslagen.', 'error');
            });
        });
    }

    // Initialize on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        activateTabFromHash();
    });
    // Also initialize on Turbo page loads
    document.addEventListener('turbo:load', activateTabFromHash);
    // Also on pageshow (for back/forward navigation)
    window.addEventListener('pageshow', activateTabFromHash);

    // Update URL hash when tab changes
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            history.replaceState(null, null, event.target.getAttribute('data-bs-target'));

            // Initialize nestable when Fields tab is shown
            if (event.target.getAttribute('data-bs-target') === '#fields') {
                initNestable();
            }
        });
    });

    // Also initialize if Fields tab is already active on page load
    document.addEventListener('DOMContentLoaded', function() {
        const activeTab = document.querySelector('[data-bs-toggle="tab"].active');
        if (activeTab && activeTab.getAttribute('data-bs-target') === '#fields') {
            initNestable();
        }
    });
    document.addEventListener('turbo:load', function() {
        const activeTab = document.querySelector('[data-bs-toggle="tab"].active');
        if (activeTab && activeTab.getAttribute('data-bs-target') === '#fields') {
            initNestable();
        }
    });
})();
