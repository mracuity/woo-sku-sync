# \# SKU Price Sync System

# 

# Sync WooCommerce product prices directly from a Google Sheets CSV URL — efficiently and safely — using background processing.

# 

# \---

# 

# \## 🚀 Overview

# 

# \*\*SKU Price Sync System\*\* is a lightweight WooCommerce plugin designed to automatically update product prices by matching SKUs from a centralized Google Sheets CSV.

# 

# It is built for scalability and multi-site usage, ensuring that each store only updates its own existing products without creating duplicates or unnecessary load on the server.

# 

# \---

# 

# \## ⚙️ Key Features

# 

# \* 🔄 \*\*Automated Price Sync\*\*

# 

# &#x20; \* Fetches product pricing from a Google Sheets CSV URL

# &#x20; \* Matches products using \*\*SKU and variation SKU\*\*

# 

# \* 🧠 \*\*Smart Update Logic\*\*

# 

# &#x20; \* Updates only existing products

# &#x20; \* Skips unchanged prices (optional optimization-ready)

# 

# \* ⚡ \*\*Background Processing\*\*

# 

# &#x20; \* Runs sync tasks without slowing down your website

# &#x20; \* Prevents timeouts on large catalogs

# 

# \* 🔒 \*\*Safe \& Controlled Execution\*\*

# 

# &#x20; \* No product creation — only updates

# &#x20; \* Built-in safeguards to prevent duplicate sync runs

# 

# \* 🌐 \*\*Multi-Site Compatible\*\*

# 

# &#x20; \* Use the same CSV across multiple WooCommerce stores

# &#x20; \* Each store updates only its own matching SKUs

# 

# \---

# 

# \## 📄 CSV Structure

# 

# Your Google Sheet must be published as a \*\*CSV\*\* and follow this format:

# 

# | SKU       | Price |

# | --------- | ----- |

# | ABC123    | 19.99 |

# | VAR-RED-M | 24.50 |

# 

# \*\*Requirements:\*\*

# 

# \* SKU must match existing WooCommerce product or variation SKU

# \* Price should be a valid numeric value

# \* First row should contain headers

# 

# \---

# 

# \## 🔗 Google Sheets Setup

# 

# 1\. Create your pricing sheet in Google Sheets

# 2\. Go to \*\*File → Share → Publish to web\*\*

# 3\. Select \*\*CSV format\*\*

# 4\. Copy the generated URL

# 5\. Paste it into the plugin settings

# 

# \---

# 

# \## 🧩 Installation

# 

# 1\. Download or clone the repository:

# 

# &#x20;  ```

# &#x20;  https://github.com/mracuity/woo-sku-sync

# &#x20;  ```

# 

# 2\. Upload the plugin folder to:

# 

# &#x20;  ```

# &#x20;  /wp-content/plugins/

# &#x20;  ```

# 

# 3\. Activate the plugin from:

# &#x20;  \*\*WordPress Admin → Plugins\*\*

# 

# \---

# 

# \## 🛠️ Configuration

# 

# After activation:

# 

# 1\. Navigate to plugin settings (if UI is enabled)

# 2\. Add your \*\*Google Sheets CSV URL\*\*

# 3\. Trigger sync manually or via scheduled process (if configured)

# 

# \---

# 

# \## 🔄 Sync Behavior

# 

# \* Matches products by \*\*SKU\*\*

# \* Supports \*\*simple and variable products\*\*

# \* Updates:

# 

# &#x20; \* Regular price (default behavior)

# \* Ignores:

# 

# &#x20; \* Missing SKUs

# &#x20; \* Invalid rows

# 

# \---

# 

# \## 🧱 System Requirements

# 

# \* WordPress \*\*5.8+\*\*

# \* PHP \*\*7.4+\*\*

# \* WooCommerce \*\*6.0+\*\*

# 

# \---

# 

# \## 🧪 Version

# 

# \*\*1.0.2\*\*

# 

# \---

# 

# \## 👤 Author

# 

# \*\*Mr. Acuity\*\*

# 🔗 https://github.com/mracuity

# 

# \---

# 

# \## 📜 License

# 

# GPL-2.0+

# 

# \---

# 

# \## 💡 Use Cases

# 

# \* Centralized pricing across multiple WooCommerce stores

# \* Bulk price updates without manual editing

# \* Dropshipping or supplier-based dynamic pricing

# \* Automated catalog maintenance

# 

# \---

# 

# \## ⚠️ Notes

# 

# \* Ensure SKU consistency across your store and CSV

# \* Large catalogs benefit from background processing (already integrated)

# \* Always test on staging before running on production

# 

# \---

# 

# \## 🔮 Future Enhancements (Planned)

# 

# \* Change detection (update only if price differs)

# \* Scheduled auto-sync (cron-based)

# \* Admin dashboard with logs \& sync status

# \* Error reporting system

# \* CSV validation layer

# 

# \---

# 

# \## 🧩 Plugin Metadata

# 

# ```

# Plugin Name: SKU Price Sync System

# Plugin URI:  https://github.com/mracuity/woo-sku-sync

# Description: Syncs WooCommerce product prices from a Google Sheets CSV URL using background processing.

# Version:     1.0.2

# Author:      Mr. Acuity

# License:     GPL-2.0+

# Text Domain: woo-sku-sync

# Requires at least: 5.8

# Requires PHP: 7.4

# WC requires at least: 6.0

# ```

# 

# \---

# 

# \## 🤝 Contributing

# 

# Feel free to fork the repository and submit pull requests for improvements or new features.

# 

# \---

# 

# \## 📬 Support

# 

# For issues or feature requests, open an issue on GitHub.

# 

# \---



