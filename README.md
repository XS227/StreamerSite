# Streamer Site Builder (skeleton)

This repository provides a plain-JS starter layout for **Streamer Site Builder**, a Novi-like visual builder rebranded for StreamerSite.

## Structure
```
builder/
  index.html           # Streamer Site Builder UI shell
  builder.js           # JS skeleton for Edit/Design/Preview modes
  styles.css           # Minimal UI styling
  config/              # Project/system/layer config
  projects/default/    # Default demo project (fjolsenbanden placeholder)
  templates/fjolsenbanden/ # Drop real fjolsenbanden template here
```

### Default project
`builder/projects/default/template` ships a placeholder Fjolsenbanden page with inline-editable content (`data-ssb-editable`). Replace it with the real Fjolsenbanden HTML/CSS/JS to ship with the builder.

### Configuration
- `builder/config/config.json` – project & system settings (publish path, assets, etc.).
- `builder/config/layers.json` – layer rules for sections, columns, and content.
- `builder/config/lang/en.json` – basic UI labels.

### Next steps
- Implement PHP endpoints for import/export/publish.
- Flesh out presets, media library, icons manager, and style manager.
- Connect layer rules to drag/drop and context menu behaviors.
