// builder.js (plain JS, ES modules allowed)

class StreamerSiteBuilder {
  constructor() {
    this.mode = "edit"; // "edit" | "design" | "preview"
    this.currentPage = null;
    this.pages = [];
    this.layersConfig = null;
    this.projectMeta = null;

    this.iframe = document.getElementById("ssb-canvas");
    this.pagesListEl = document.getElementById("ssb-pages-list");

    this.init();
  }

  async init() {
    await this.loadConfig();
    await this.loadDefaultProjectIfNeeded();
    this.bindUI();

    if (this.pages.length) {
      const requestedPage = new URLSearchParams(window.location.search).get("page");
      const normalizedRequest = requestedPage?.replace(/^\//, "");

      const initialPage = requestedPage
        ? this.pages.find((page) =>
            page.file.endsWith(normalizedRequest) || page.file.endsWith(`/${normalizedRequest}`)
          ) || this.pages[0]
        : this.pages[0];

      this.loadPage(initialPage); // load home page or requested page
    }
  }

  async loadConfig() {
    // Load config.json & layers.json (simplified)
    const [configRes, layersRes] = await Promise.all([
      fetch("./config/config.json"),
      fetch("./config/layers.json"),
    ]);

    this.config = await configRes.json();
    this.layersConfig = await layersRes.json();

    this.setProjectName(this.config.projectName);
  }

  async loadDefaultProjectIfNeeded() {
    const projectMetaPath = this.config.projectMeta || "projects/default/project.json";
    const resolvedMetaPath = projectMetaPath.startsWith("./")
      ? projectMetaPath
      : `./${projectMetaPath}`;

    try {
      const response = await fetch(resolvedMetaPath);

      if (!response.ok) {
        throw new Error(`Unable to load project meta: ${response.status}`);
      }

      this.projectMeta = await response.json();
      const templatePath = this.projectMeta.templatePath || this.config.defaultProject || "projects/default/template";
      const templateBase = templatePath.startsWith("./")
        ? templatePath.replace(/\/$/, "")
        : `./${templatePath.replace(/\/$/, "")}`;
      const projectRoot = templateBase.replace(/\/[^/]+$/, "");

      this.pages = (this.projectMeta.pages || []).map((page) => ({
        ...page,
        file: `${templateBase}/${page.file}`,
        preview: page.preview ? `${projectRoot}/${page.preview}` : undefined,
      }));

      this.setProjectName(this.projectMeta.name || this.config.projectName);
    } catch (error) {
      console.error(error);
      // Fallback to a single default page if project data cannot be loaded
      this.pages = [
        {
          id: "index",
          title: "Home",
          file: "./projects/default/template/index.html",
          isHome: true,
        },
      ];
    }

    this.renderPagesList();
  }

  setProjectName(name) {
    const projectNameEl = document.getElementById("ssb-current-project");

    if (projectNameEl && name) {
      projectNameEl.textContent = name;
    }
  }

  renderPagesList() {
    this.pagesListEl.innerHTML = "";
    this.pages.forEach((page) => {
      const btn = document.createElement("button");
      btn.className = "ssb-page-item";
      btn.textContent = page.title + (page.isHome ? " (home)" : "");
      btn.addEventListener("click", () => this.loadPage(page));
      this.pagesListEl.appendChild(btn);
    });
  }

  async loadPage(page) {
    this.currentPage = page;
    this.iframe.src = page.file;

    // Once iframe is loaded, wire editing features
    this.iframe.onload = () => {
      this.setupCanvas();
      this.refreshDevEditors();
    };
  }

  setupCanvas() {
    const doc = this.iframe.contentDocument;
    if (!doc) return;

    // Example: enable inline text editing in Edit mode
    this.toggleEditMode(this.mode === "edit");

    // Enable click selection & layer highlighting
    doc.addEventListener("click", (e) => {
      if (this.mode === "preview") return;
      const target = e.target;
      this.handleCanvasClick(target, e);
    });
  }

  handleCanvasClick(el, event) {
    event.preventDefault();
    event.stopPropagation();

    // TODO: use layersConfig to determine if this element is a "layer"
    // For now, we just log it.
    console.log("Selected element:", el);

    if (this.mode === "design") {
      this.highlightElementInHtmlEditor(el);
    }
  }

  highlightElementInHtmlEditor(el) {
    // Simplified: just dump full HTML into editor
    const htmlEditor = document.getElementById("ssb-dev-html");
    const doc = this.iframe.contentDocument;
    if (!doc || !htmlEditor) return;
    htmlEditor.value = doc.documentElement.outerHTML;
    // Real implementation would find the element range and highlight it.
  }

  refreshDevEditors() {
    if (this.mode !== "design") return;
    const doc = this.iframe.contentDocument;
    if (!doc) return;

    document.getElementById("ssb-dev-html").value =
      doc.documentElement.outerHTML;

    // CSS & JS: you’d parse linked files and load contents here
  }

  bindUI() {
    // Mode switcher
    document
      .querySelectorAll(".ssb-mode-btn")
      .forEach((btn) =>
        btn.addEventListener("click", () =>
          this.changeMode(btn.dataset.mode)
        )
      );

    // Dev tabs
    document.querySelectorAll(".ssb-dev-tab").forEach((tab) => {
      tab.addEventListener("click", () => this.switchDevTab(tab));
    });

    // Main menu
    const menuToggle = document.getElementById("ssb-main-menu-toggle");
    const menuClose = document.getElementById("ssb-main-menu-close");
    const menu = document.getElementById("ssb-main-menu");

    menuToggle.addEventListener("click", () => menu.classList.remove("ssb-hidden"));
    menuClose.addEventListener("click", () => menu.classList.add("ssb-hidden"));

    // Save & Publish
    document
      .getElementById("ssb-save-project")
      .addEventListener("click", () => this.saveProject());

    document
      .getElementById("ssb-publish-project")
      .addEventListener("click", () => this.publishProject());
  }

  changeMode(mode) {
    this.mode = mode;

    // Toggle active button
    document.querySelectorAll(".ssb-mode-btn").forEach((btn) => {
      btn.classList.toggle(
        "ssb-mode-btn--active",
        btn.dataset.mode === mode
      );
    });

    if (mode === "preview") {
      this.toggleEditMode(false);
      // In preview, we do not allow selection
    } else if (mode === "edit") {
      this.toggleEditMode(true);
    } else if (mode === "design") {
      this.toggleEditMode(false);
      this.refreshDevEditors();
    }
  }

  toggleEditMode(isEditable) {
    const doc = this.iframe.contentDocument;
    if (!doc) return;

    // Simple strategy: all text nodes inside elements with [data-ssb-editable]
    const editableNodes = doc.querySelectorAll("[data-ssb-editable]");
    editableNodes.forEach((el) => {
      el.contentEditable = isEditable ? "true" : "false";
    });
  }

  switchDevTab(tabBtn) {
    const tabName = tabBtn.dataset.devTab;

    document.querySelectorAll(".ssb-dev-tab").forEach((btn) => {
      btn.classList.toggle("ssb-dev-tab--active", btn === tabBtn);
    });

    // Hide all editors/panels
    document.querySelectorAll(".ssb-dev-editor, .ssb-dev-panel").forEach((p) => {
      p.classList.add("ssb-hidden");
    });

    switch (tabName) {
      case "html":
        document.getElementById("ssb-dev-html").classList.remove("ssb-hidden");
        break;
      case "css":
        document.getElementById("ssb-dev-css").classList.remove("ssb-hidden");
        break;
      case "js":
        document.getElementById("ssb-dev-js").classList.remove("ssb-hidden");
        break;
      case "presets":
        document
          .getElementById("ssb-dev-presets")
          .classList.remove("ssb-hidden");
        break;
      case "plugins":
        document
          .getElementById("ssb-dev-plugins")
          .classList.remove("ssb-hidden");
        break;
      case "styles-manager":
        document
          .getElementById("ssb-dev-styles-manager")
          .classList.remove("ssb-hidden");
        break;
    }
  }

  async saveProject() {
    // In Novi this saves HTML/CSS/JS, presets, config etc.
    // Here we’d send the current iframe DOM & config to a PHP endpoint.
    alert("Save project (to be implemented in PHP)");
  }

  async publishProject() {
    // Call publish.php with current config
    alert("Publish project (to be implemented in PHP)");
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new StreamerSiteBuilder();
});
