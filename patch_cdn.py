import re

with open("index.html", "r", encoding="utf-8") as f:
    content = f.read()

content = content.replace('<script src="https://cdn.tailwindcss.com"></script>', '<script src="assets/js/tailwindcss.js"></script>')
content = content.replace('<script src="https://unpkg.com/lucide@latest"></script>', '<script src="assets/js/lucide.min.js"></script>')
content = content.replace('<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>', '<script src="assets/js/sweetalert2.min.js"></script>')
content = content.replace('<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>', '<script src="assets/js/xlsx.full.min.js"></script>')
content = content.replace('<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>', '<script src="assets/js/html2pdf.bundle.min.js"></script>')

# Remove google fonts
content = re.sub(r'<link href="https://fonts.googleapis.com[^>]+>', '', content)

# Add local fonts placeholder
local_fonts = """<!-- Fonts Offline -->
    <link href="assets/css/inter.css" rel="stylesheet">
    <link href="assets/css/material-icons.css" rel="stylesheet">"""

content = content.replace('<!-- Libraries -->', '<!-- Libraries -->\n    ' + local_fonts)

with open("index.html", "w", encoding="utf-8") as f:
    f.write(content)
print("Successfully patched CDNs")
