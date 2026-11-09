/*
 * Videos for logged in visitors on a blog post (blog/detail, video.protected-video): no right-click menu,
 * so there is no "Save video as…". The download button is hidden by controlslist="nodownload" and the
 * server refuses the video outside the player (App\Controller\BlogVideoController).
 */
document.querySelectorAll('video.protected-video').forEach(function (video) {
    video.addEventListener('contextmenu', event => event.preventDefault());
});
