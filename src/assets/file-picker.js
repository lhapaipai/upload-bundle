
import { createFormFilePicker } from "mini-file-manager";
import "mini-file-manager/dist/style.css";

document.querySelectorAll(".file-picker").forEach((elt) => {
  createFormFilePicker(elt);
});
