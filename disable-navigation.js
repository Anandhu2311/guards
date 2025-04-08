
  // Disable back and forward navigation
  history.pushState(null, null, location.href);
  window.onpopstate = function () {
    history.pushState(null, null, location.href);
  };

  // Disable right-click menu (optional)
  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
  });

