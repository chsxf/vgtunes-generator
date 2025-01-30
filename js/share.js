let shareTimeout = null;

function setupSharing() {
  const shareButtons = document.getElementsByClassName("shbtn");
  for (const shareButton of shareButtons) {
    shareButton.addEventListener("click", onShareButtonClicked);
  }
}

async function onShareButtonClicked(e) {
  e.preventDefault();
  e.stopPropagation();

  const shareData = {
    title: "VGTunes",
    text: this.attributes["data-share-text"].value,
    url: this.attributes["data-share-url"].value,
  };

  if (navigator.share) {
    await navigator.share(shareData);
  } else if (navigator.clipboard) {
    await navigator.clipboard.writeText(shareData.url);
    showCopiedToClipboardToast();
  }
}

function showCopiedToClipboardToast() {
  if (shareTimeout !== null) {
    window.clearTimeout(shareTimeout);
  }

  const toastElement = document.getElementById("copied-to-clipboard-toast");
  toastElement.style.setProperty("visibility", "visible");
  shareTimeout = window.setTimeout(() => {
    toastElement.style.setProperty("visibility", "hidden");
  }, 3000);
}

(function () {
  setupSharing();
})();
