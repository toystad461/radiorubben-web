import { access, readFile } from "node:fs/promises";

const requiredFiles = [
  "dist/index.html",
  "dist/style.css",
  "dist/logo.png",
  "dist/lan.png",
  ".openai/hosting.json",
];

for (const file of requiredFiles) {
  await access(file);
}

const html = await readFile("dist/index.html", "utf8");
const requiredContent = [
  '<html lang="nb">',
  "Radio Rubben",
  "LOKAL",
  "INKLUDERENDE",
  "VERDIG",
  "ENGASJERENDE",
  'href="style.css"',
];

for (const content of requiredContent) {
  if (!html.includes(content)) {
    throw new Error(`Mangler forventet innhold i dist/index.html: ${content}`);
  }
}

console.log("Kontroll fullført: nødvendige filer og merkevareelementer finnes.");
