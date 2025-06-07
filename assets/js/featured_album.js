let feedRequest = new XMLHttpRequest();
feedRequest.addEventListener("load", onFeedLoaded);

const postRegex = /^the #vgtunes of the (day|week) is/i;

function onFeedLoaded() {
  const feed = JSON.parse(feedRequest.responseText);
  for (const feedItem of feed.feed) {
    if (postRegex.test(feedItem.post.record.text)) {
      parseVGTunesOfTheDayPost(feedItem.post);
      break;
    }
  }
}

function parseVGTunesOfTheDayPost(post) {
  let featureAlbumUri = null;
  for (const facet of post.record.facets) {
    for (const feature of facet.features) {
      if (feature["$type"] == "app.bsky.richtext.facet#link") {
        featureAlbumUri = feature.uri;
        break;
      }
    }
  }

  if (featureAlbumUri !== null) {
    const re = /\/([^\/]+)\/$/;
    const reResult = re.exec(featureAlbumUri);
    if (reResult != null) {
      showFeaturedAlbum(reResult[1]);
    }
  }
}

function expandPlatform(shortPlatform) {
  switch (shortPlatform) {
    case "am":
      return "apple_music";
    case "b":
      return "bandcamp";
    case "d":
      return "deezer";
    case "s":
      return "spotify";
    case "st":
      return "steam_game";
    case "ss":
      return "steam_soundtrack";
    default:
      return null;
  }
}

function showFeaturedAlbum(albumSlug) {
  const albumCoverURL = `${baseCoverURL}${albumSlug}/cover_250.webp`;
  const albumData = searchIndex.albums[albumSlug];
  const albumURL = `${baseAlbumURL}${albumSlug}/`;

  let artistArray = [];
  let rawArtistsArray = [];
  for (const artistIndex of albumData.a) {
    const artistData = searchIndex.artists[artistIndex];
    artistArray.push(
      `<a href="${baseArtistURL}${artistData[1]}/">${artistData[0]}</a>`
    );
    rawArtistsArray.push(artistData[0]);
  }

  const albumAltString = `${albumData.t} - ${rawArtistsArray.join(", ")}`;

  const block = document.getElementById("featured-album-block");

  const blurredCoverDiv = getFirstElementWithClassName(block, "blurred-cover");
  blurredCoverDiv.style.setProperty(
    "background-image",
    `url('${albumCoverURL}')`
  );

  const albumImage = getFirstElementWithClassName(block, "album");
  albumImage.setAttribute("href", albumURL);
  albumImage.setAttribute("title", albumAltString);
  albumImage.style.setProperty("background-image", `url('${albumCoverURL}')`);

  const albumTitleSpan = getFirstElementWithClassName(block, "album-title");
  const albumTitleA = getFirstElementWithTagName(albumTitleSpan, "a");
  albumTitleA.setAttribute("href", albumURL);
  albumTitleA.innerText = albumData.t;

  const albumArtistSpan = getFirstElementWithClassName(block, "album-artist");
  albumArtistSpan.innerHTML = artistArray.join(", ");

  let platformArray = [];
  for (const shortPlatform of albumData.i) {
    const expandedPlatform = expandPlatform(shortPlatform);
    if (expandedPlatform !== null) {
      const platformName = platformNames[expandedPlatform];
      const platformTag = `<img src="/images/platform-icons/${expandedPlatform}.png" title="${platformName}">`;
      platformArray.push(platformTag);
    }
  }

  const platformDiv = getFirstElementWithClassName(block, "platforms");
  platformDiv.innerHTML = platformArray.join(" ");

  const shareButton = getFirstElementWithClassName(block, "shbtn");
  shareButton.setAttribute("data-share-url", albumURL);
  shareButton.setAttribute("data-share-text", albumAltString);
  shareButton.style.removeProperty("visibility");

  block.style.setProperty("display", "block");
}

function getFirstElementWithClassName(parentNode, className) {
  const elements = parentNode.getElementsByClassName(className);
  return elements[0];
}

function getFirstElementWithTagName(parentNode, tagName) {
  const elements = parentNode.getElementsByTagName(tagName);
  return elements[0];
}

function loadBlueSkyFeed() {
  const feedDid =
    "at://did:plc:pfrhpyiromrpkxqpa6xnuczm/app.bsky.feed.generator/vgtunes";
  const publicApiURL = `https://public.api.bsky.app/xrpc/app.bsky.feed.getFeed?feed=${encodeURI(
    feedDid
  )}&limit=5`;

  feedRequest.open("get", publicApiURL);
  feedRequest.send();
}

function waitForSearchIndex() {
  let interval = setInterval(() => {
    if (searchIndex !== null) {
      clearInterval(interval);
      loadBlueSkyFeed();
    }
  }, 1000);
}

(function () {
  waitForSearchIndex();
})();
