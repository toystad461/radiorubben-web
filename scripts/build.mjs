import { stat } from "node:fs/promises";
import "./check.mjs";

const output = await stat("dist");
if (!output.isDirectory()) {
  throw new Error("dist må være en mappe.");
}

console.log("Bygg fullført: dist er klar for publisering.");
