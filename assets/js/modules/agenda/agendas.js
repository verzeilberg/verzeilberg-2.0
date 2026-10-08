/* Agenda management (admin/agenda/agendas): copy buttons for the iCal feed URLs. */
document.querySelectorAll('.js-copy').forEach(function(button) {
    button.addEventListener('click', function() {
        navigator.clipboard?.writeText(button.dataset.copy).then(function() {
            button.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(function() { button.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
        });
    });
});
