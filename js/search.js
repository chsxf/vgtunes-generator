let searchIndexRequest = new XMLHttpRequest();
searchIndexRequest.addEventListener("load", onSearchIndexLoaded);

let searchIndex = null;

let searchTimeout = null;

function onSearchIndexLoaded() {
  searchIndex = JSON.parse(searchIndexRequest.responseText);
  let searchField = document.getElementById("search-terms");
  searchField.setAttribute("placeholder", "Search for albums");
  searchField.removeAttribute("disabled");
  searchField.addEventListener("input", onSearchInput);
}

function onSearchInput(e) {
  e.preventDefault();
  e.stopPropagation();

  if (searchTimeout !== null) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(
    refreshSearch,
    300,
    this.value.toLowerCase().trim()
  );
}

function refreshSearch(searchTerm) {
  let foundSlugs = [];

  if (searchTerm.length > 0) {
    let matchingArtists = [];
    for (let i = 0; i < searchIndex.artists.length; i++) {
      if (searchIndex.artists[i].toLowerCase().indexOf(searchTerm) >= 0) {
        matchingArtists.push(i);
      }
    }

    for (var albumSlug in searchIndex.albums) {
      let album = searchIndex.albums[albumSlug];
      if (
        album.t.toLowerCase().indexOf(searchTerm) >= 0 ||
        matchingArtists.indexOf(album.a) >= 0
      ) {
        foundSlugs.push(albumSlug);
      }
    }

    updateSearchResults(foundSlugs);
  } else {
    hideSearch(false);
  }
}

function hideSearch(clearTextField) {
  const searchDropdown = document.getElementById("results-dropdown");
  searchDropdown.style.setProperty("visibility", "hidden");

  const searchCancel = document.getElementById("search-cancel");
  searchCancel.style.setProperty("display", "none");

  if (clearTextField) {
    const searchField = document.getElementById("search-terms");
    searchField.value = "";
  }
}

function updateSearchResults(foundSlugs) {
  const searchList = document.getElementById("results-list");

  for (let i = searchList.childElementCount - 1; i >= 1; i--) {
    var child = searchList.children.item(i);
    child.remove();
  }

  const noResultEntry = searchList.getElementsByClassName("no-result")[0];
  if (foundSlugs.length == 0) {
    noResultEntry.style.setProperty("display", "block");
  } else {
    noResultEntry.style.setProperty("display", "none");

    for (var slug of foundSlugs) {
      createResultNode(slug, searchList);
    }
  }

  const searchDropdown = document.getElementById("results-dropdown");
  searchDropdown.style.setProperty("visibility", "visible");

  const searchCancel = document.getElementById("search-cancel");
  searchCancel.style.setProperty("display", "block");
}

function createResultNode(withSlug, inParentNode) {
  const album = searchIndex.albums[withSlug];

  const li = document.createElement("li");
  li.classList.add("result");
  li.setAttribute("data-url", `/albums/${withSlug}/`);

  const img = document.createElement("img");
  img.setAttribute(
    "src",
    `https://images.vgtunes.chsxf.dev/covers/${withSlug}/cover_100.webp`
  );
  li.appendChild(img);

  const titleSpan = document.createElement("span");
  titleSpan.classList.add("title");
  titleSpan.appendChild(document.createTextNode(album.t));
  li.appendChild(titleSpan);

  const artistSpan = document.createElement("span");
  artistSpan.classList.add("artist");
  const artistName = searchIndex.artists[album.a];
  artistSpan.appendChild(document.createTextNode(artistName));
  li.appendChild(artistSpan);

  inParentNode.appendChild(li);

  li.addEventListener("click", onSearchResultClicked);
}

function onSearchResultClicked(e) {
  e.preventDefault();
  e.stopPropagation();
  document.location.href = this.attributes["data-url"].value;
}

function onSearchCancelClicked(e) {
  console.log("clicked");
  e.preventDefault();
  e.stopPropagation();
  hideSearch(true);
}

function setupSearch() {
  searchIndexRequest.open("get", "/searchIndex.json?t=" + Date.now());
  searchIndexRequest.send();

  const searchCancel = document.getElementById("search-cancel");
  searchCancel.addEventListener("click", onSearchCancelClicked);
}

(function () {
  setupSearch();
})();
