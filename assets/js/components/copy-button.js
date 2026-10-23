/* Copy buttons: <button class="js-copy" data-copy="text"> copies the text and shows a check mark for a moment.
   Used for the agenda feed URLs and the preview link of a blog post. */
document.querySelectorAll('.js-copy').forEach(function(button) {
    button.addEventListener('click', function() {
        navigator.clipboard?.writeText(button.dataset.copy).then(function() {
            button.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(function() { button.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
        });
    });
});
