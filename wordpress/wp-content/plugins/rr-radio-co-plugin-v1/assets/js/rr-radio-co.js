document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.rr-audio').forEach(function (audio) {
    audio.addEventListener('play', function () {
      document.querySelectorAll('.rr-audio').forEach(function (other) {
        if (other !== audio) {
          other.pause();
        }
      });
    });
  });
});
