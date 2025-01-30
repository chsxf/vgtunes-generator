const CHECKBOX_ID = "analytics-consent-checkbox";
const NAVDATA_VERSION = 1;
const NAVDATA_KEY = "navigation_data";
const MAX_NAVIGATION_ENTRIES = 50;

function initNavigationData() {
  return {
    version: NAVDATA_VERSION,
    consent: true,
    entries: [],
  };
}

function loadNavigationData() {
  let navigationData = window.localStorage.getItem(NAVDATA_KEY);
  if (navigationData !== null) {
    try {
      navigationData = JSON.parse(navigationData);
      if (navigationData !== null) {
        if (navigationData.version > NAVDATA_VERSION) {
          navigationData = null;
        } else {
          navigationData = updateNavigationDataStructure(navigationData);
        }
      }
    } catch (e) {
      console.log(e);
      navigationData = null;
    }
  }
  if (navigationData === null) {
    navigationData = initNavigationData();
  }
  return navigationData;
}

function updateNavigationDataStructure(navigationData) {
  return navigationData;
}

function handleAnalyticsRequest(navigationData) {
  let shouldReport = true;

  for (let i = 0; i < navigationData.entries.length; i++) {
    const entry = navigationData.entries[i];
    if (entry[0] == document.location.pathname) {
      const parsedTimestamp = entry[1];
      if (parsedTimestamp > Date.now() - 86400000) {
        shouldReport = false;
      } else {
        navigationData.entries.splice(i, 1);
      }
      break;
    }
  }

  while (navigationData.entries.length >= MAX_NAVIGATION_ENTRIES) {
    navigationData.entries.splice(0, 1);
  }

  if (shouldReport) {
    navigationData.entries.push([document.location.pathname, Date.now()]);
    window.localStorage.setItem(NAVDATA_KEY, JSON.stringify(navigationData));

    // BEGIN REPLACE ANALYTICS_HOST const analyticsHost = "{ANALYTICS_HOST}";
    const analyticsHost = "https://analytics.chsxf.dev";
    // END REPLACE ANALYTICS_HOST

    let documentPath = document.location.pathname;
    if (/index\.(html|php)$/.test(documentPath)) {
      const lastIndex = documentPath.lastIndexOf("/");
      documentPath = documentPath.substring(0, lastIndex + 1);
    }

    let analyticsURL = `${analyticsHost}/Stats.add/?domain=${encodeURI(
      document.location.hostname
    )}&path=${encodeURI(documentPath)}`;
    if (document.referrer) {
      let documentReferrerURL = new URL(document.referrer);
      if (documentReferrerURL.hostname != document.location.hostname) {
        analyticsURL += `&referrer=${encodeURI(document.referrer)}`;
      }
    }

    let analyticsXMLHTTPRequest = new XMLHttpRequest();
    analyticsXMLHTTPRequest.open("get", analyticsURL);
    analyticsXMLHTTPRequest.send();
  }
}

function setConsentCheckboxIfAvailable(consented) {
  let checkboxElement = document.getElementById(CHECKBOX_ID);
  if (checkboxElement !== null) {
    checkboxElement.checked = consented;
  }
}

function onConsentCheckboxChanged(e) {
  e.preventDefault();
  e.stopPropagation();

  let navigationData = loadNavigationData();
  navigationData.consent = this.checked;
  window.localStorage.setItem(NAVDATA_KEY, JSON.stringify(navigationData));
}

function initializeAnalytics() {
  let checkboxElement = document.getElementById(CHECKBOX_ID);
  if (checkboxElement !== null) {
    checkboxElement.addEventListener("change", onConsentCheckboxChanged);
  }

  let navigationData = loadNavigationData();
  setConsentCheckboxIfAvailable(navigationData.consent);
  if (navigationData.consent) {
    handleAnalyticsRequest(navigationData);
  }
}

(function () {
  initializeAnalytics();
})();
