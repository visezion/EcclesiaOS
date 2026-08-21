document.addEventListener('DOMContentLoaded', () => {
  const videoUrl = document.querySelector('meta[name="church-hero-video"]')?.content;
  const media = document.querySelector('.hero-media, .crepa-art');
  if (videoUrl && media) {
    const video = document.createElement('video');
    video.className = 'hero-video';
    video.autoplay = true;
    video.muted = true;
    video.loop = true;
    video.playsInline = true;
    video.src = videoUrl;
    media.prepend(video);
  }
});
