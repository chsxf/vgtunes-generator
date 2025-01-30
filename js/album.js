function setupBackLinks() {
  if (document.referrer) {
    const currentHost = document.location.host;
    const refererrerURL = new URL(document.referrer);
    if (refererrerURL.host == currentHost) {
      const backButtonElements = document.getElementsByClassName("back-button");
      for (let button of backButtonElements) {
        button.classList.remove("hidden");
      }
    }
  }
}

(function () {
  setupBackLinks();
})();
