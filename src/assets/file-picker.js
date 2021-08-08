import "mini-file-manager/dist/style.css";
import "./file-picker.scss";

import { openFileManager } from "mini-file-manager";

class FilePicker {
  constructor(elt) {
    this.filePickerElt = elt;
    this.browseBtn = elt.querySelector("button.browse");
    this.browseA = elt.querySelector("a.browse");
    this.removeA = elt.querySelector("a.remove");

    this.input = document.getElementById(this.browseBtn.dataset.target);
    this.previewImage = elt.querySelector(".preview img");
    this.previewFilename = elt.querySelector(".preview .filename");
    this.previewFilter = this.previewImage.dataset.filter;
    this.previewType = this.previewImage.dataset.type;
    this.fileManagerOptions = JSON.parse(this.browseBtn.dataset.filemanager);

    this.handleClick = this.handleClick.bind(this);
    this.onSelected = this.onSelected.bind(this);
    this.onAbort = this.onAbort.bind(this);
    this.onRemove = this.onRemove.bind(this);

    this.browseBtn.addEventListener("click", this.handleClick);
    this.browseA.addEventListener("click", this.handleClick);
    this.removeA.addEventListener("click", this.onRemove);
  }

  handleClick(e) {
    e.preventDefault();
    openFileManager(this.fileManagerOptions, this.onSelected, this.onAbort);
  }

  onSelected(files) {
    let file = files[0];
    console.log(file, this.previewType);
    if (this.previewType === "image") {
      if (file.thumbnails) {
        this.previewImage.src = file.thumbnails[this.previewFilter];
      } else {
        this.previewImage.src = file.url;
      }
    } else {
      this.previewImage.src = file.icon;
      this.previewFilename.innerText = file.filename;
    }
    this.input.value = file.uploadRelativePath;
    this.filePickerElt.classList.add("with-value");
  }

  onRemove(e) {
    e.preventDefault();
    this.previewImage.src = "";
    this.previewFilename.innerText = "";
    this.input.value = "";
    this.filePickerElt.classList.remove("with-value");
  }

  onAbort() {}
}

document.querySelectorAll(".form-group.file-picker").forEach((elt) => {
  new FilePicker(elt);
});
