/*
 * Photo carousel on a blog post (blog/detail): keeps the active thumbnail in view while browsing.
 * Bootstrap itself moves the active state, because the thumbnails are the carousel's indicators.
 */
document.addEventListener('DOMContentLoaded', function () {
    const carousel = document.getElementById('postCarousel');
    if (!carousel) {
        return;
    }

    const thumbs = carousel.querySelector('.post-carousel-thumbs');
    carousel.addEventListener('slid.bs.carousel', function (event) {
        const thumb = thumbs.children[event.to];
        if (thumb) {
            // Scroll the strip only, not the whole page
            thumbs.scrollTo({
                left: thumb.offsetLeft - (thumbs.clientWidth - thumb.offsetWidth) / 2,
                behavior: 'smooth',
            });
        }
    });
});
