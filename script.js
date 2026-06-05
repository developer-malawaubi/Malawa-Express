const searchInput = document.querySelector("#searchInput");
const tabs = document.querySelectorAll("#categoryTabs a");
const cards = document.querySelectorAll(".product-card");
const emptyState = document.querySelector("#emptyState");
const headerMenu = document.querySelector(".header-menu");
const headerSearch = document.querySelector(".header-search");
const headerSearchInput = document.querySelector("#headerSearchInput");
const headerSearchResults = document.querySelectorAll(".header-search-result");
const headerSearchEmpty = document.querySelector("#headerSearchEmpty");
const validCategories = ["all", "electronics", "clothing", "handicrafts", "household", "other"];
const params = new URLSearchParams(window.location.search);
let activeCategory = validCategories.includes(params.get("category")) ? params.get("category") : "all";

function applyFilters() {
  const query = (searchInput?.value || "").trim().toLowerCase();
  let visible = 0;
  cards.forEach((card) => {
    const categoryMatch = activeCategory === "all" || card.dataset.category === activeCategory;
    const searchMatch = !query || (card.dataset.search || card.dataset.title || "").includes(query);
    const show = categoryMatch && searchMatch;
    card.hidden = !show;
    if (show) visible += 1;
  });
  if (emptyState) emptyState.hidden = visible > 0;
}

function setActiveCategory(category) {
  activeCategory = validCategories.includes(category) ? category : "all";
  tabs.forEach((item) => item.classList.toggle("active", item.dataset.category === activeCategory));
  applyFilters();
}

tabs.forEach((tab) => {
  tab.addEventListener("click", (event) => {
    const category = tab.dataset.category;
    if (!category) return;
    event.preventDefault();
    setActiveCategory(category);
  });
});

searchInput?.addEventListener("input", applyFilters);
setActiveCategory(activeCategory);

function filterHeaderSearch() {
  const query = (headerSearchInput?.value || "").trim().toLowerCase();
  const terms = query.split(/\s+/).filter(Boolean);
  let visible = 0;

  headerSearchResults.forEach((result) => {
    const searchText = result.dataset.search || "";
    const show = terms.length === 0 || terms.every((term) => searchText.includes(term));
    result.hidden = !show;
    if (show) visible += 1;
  });
  if (headerSearchEmpty) headerSearchEmpty.hidden = visible > 0;
}

headerSearchInput?.addEventListener("input", filterHeaderSearch);

headerSearch?.addEventListener("toggle", () => {
  if (headerSearch.open) {
    if (headerMenu) headerMenu.open = false;
    filterHeaderSearch();
    window.setTimeout(() => headerSearchInput?.focus(), 0);
  }
});

headerMenu?.addEventListener("toggle", () => {
  if (headerMenu.open && headerSearch) headerSearch.open = false;
});

document.addEventListener("click", (event) => {
  if (headerMenu && !headerMenu.contains(event.target)) headerMenu.open = false;
  if (headerSearch && !headerSearch.contains(event.target)) headerSearch.open = false;
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && headerMenu) headerMenu.open = false;
  if (event.key === "Escape" && headerSearch) headerSearch.open = false;
});
