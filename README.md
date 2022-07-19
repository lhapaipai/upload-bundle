<p align="center">
  <img width="100" src="https://raw.githubusercontent.com/lhapaipai/upload-bundle/main/docs/symfony.svg" alt="Symfony logo">
</p>

# UploadBundle for your Symfony application.

This Symfony bundle provides :

- Upload Helpers
- Endpoints for a [Mini File Manager](https://github.com/lhapaipai/mini-file-manager) in your Symfony Application.
- Twig functions for your uploaded files
- FilePickerType for your forms

## Installation

```console
composer require pentatrion/upload-bundle
```

Recommended optional dependencies

- symfony/validator : required for upload file validation
- liip/imagine-bundle : required to use thumbnails with your file manager.
- imagine/imagine : required for image modification (resize, crop, rotation)

```console
composer require symfony/validator liip/imagine-bundle imagine/imagine
```

Other dependencies

- symfony/security-bundle : only required with FilePickerType

If you have installed `liip/imagine-bundle`, configure at least the `small` filter for your thumbnails.

```yaml
# config/packages/pentatrion_upload.yaml
# default configuration
pentatrion_upload:
  liip_filters: ["small"]
```

```yaml
# config/packages/liip_imagine.yaml
liip_imagine:
  driver: "gd"

  # define filters defined in pentatrion_upload.liip_filters
  filter_sets:
    small:
      filters:
        thumbnail: { size: [250, 250], mode: inset, allow_upscale: true }
```

Create directories with Apache user access in upload path and liipImagineBundle cache path (`public/uploads`, `public/media`)

```console
mkdir public/{uploads,media}
chmod 777 public/{uploads,media}
```



## Utilisation

### FileHelper

in your FormType create a non mapped FileType

```php
use App\Entity\Post;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;


class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // ...
            ->add('file', FileType::class, [
                'mapped' => false,
                'required' => false
            ])
        ;
    }
    // ...
}
```

in your Controller

```php
class PostController extends AbstractController
{
    /**
     * @Route("/{id}/edit", name="post_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Post $post, FileHelper $fileHelper): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form['file']->getData();
            if ($file) {
                $fileInfos = $fileHelper->uploadFile($file, 'posts');

                //  posts/my-image.jpg
                $post->setImage($fileInfos['uploadRelativePath']);
            }

            $this->getDoctrine()->getManager()->flush();
            // ...
        }
        // ...
    }
}
```

```php
$file // file from $_FILES
$directory = 'relative/path'; // path to add into public_uploads.

// configured in config/packages/pentatrion_upload.yaml
// pentatrion_upload.origins.<origin>
// default value public_uplads -> "%kernel.project_dir%/public/uploads"
$originName = 'public_uploads';

$options = [
  'forceFilename' => 'beach.jpg',
  'prefix' => null,           // prefix your file name 'img-'
  'guessExtension' => false,  // guess Extension from content
                              //(if you don't trust the uploader)
  'urlize' => true,           // my File Name.jPg -> my-file-name.jpg
  'unique' => true,           // suffixe your file with hash
                              // beach-[hash].jpg
];

$fileInfos = $fileHelper->uploadFile($file, $directory, $originName, $options);

print_r($fileInfos);
```

```json
{
  "inode": 1575770,
  "id": "@public_uploads:beach.jpg",
  "filename": "beach.jpg",
  "directory": "",
  "uploadRelativePath": "beach.jpg",
  "mimeType": "image/jpeg",
  "mimeGroup": "image",
  "type": "file",
  "uploader": "John Doe",
  "origin": "public_uploads",
  "size": 134558,
  "humanSize": "131.4 Ko",
  "createdAt": "2021-08-07T23:12:09+02:00",
  "isDir": false,
  "url": "http://mini-file-manager.local/uploads/beach.jpg",
  "urlTimestamped": "http://mini-file-manager.local/uploads/beach.jpg?1628410071",
  "icon": "/file-manager/icons/image-jpg.svg",
  "details": { "type": "image", "width": 1200, "height": 1200, "ratio": 1 },
  "thumbnails": {
    "small": "http://mini-file-manager.local/media/cache/small/uploads/beach.jpg?1628410071"
  }
}
```

### Twig functions

The bundle provide 2 twig functions :

- uploaded_file_web_path('path/to/file', '<origin>')
- uploaded_image_filtered('path/to/file', 'filter', '<origin>')

```php
class PageController extends AbstractController
{
    #[Route('/page/{slug}', name: 'page_show')]
    public function show(Page $page): Response
    {
        return $this->render('page/show.html.twig', [
            'page' => $page
        ]);
    }
```

in your `page/show.html.twig` template

```twig
<!-- folder/my-uploaded-file.pdf -->
{{ page.file }}

<!-- /uploads/folder/my-uploaded-file.pdf -->
{{ uploaded_file_web_path(page.file) }}

<!-- /media-manager/get/show/private_uploads/folder/my-uploaded-file.pdf -->
{{ uploaded_file_web_path(page.file, "private_uploads") }}

<!-- for your original -->
<!-- <img src="/uploads/folder/logo.jpg"/> -->
<img src="{{ uploaded_file_web_path(page.image) }}"/>

<!-- for your cropped image (250x250px) -->
<!-- <img src="http://localhost/media/cache/resolve/small/posts/logo.jpg"/> -->
<img src="{{ uploaded_image_filtered(page.image, 'small') }}"/>

<!-- <img src="http://localhost/media/cache/resolve/small/posts/logo.jpg"/> -->
<img src="{{ uploaded_image_filtered(page.image, 'small', 'private_uploads') }}"/>
```

## API Helper

```php
#[Route('/api', name: 'api_', defaults:["_format"=>"json"])]
class ApiController extends AbstractController
{

    #[Route('/page/{id}', name: 'projects')]
    public function showProjects(Page $page, UploadedFileHelper $fileInfosHelper): Response
    {
        // hydrate image field, with original/small/large webpaths.
        $page = $fileInfosHelper->hydrateEntityWithUploadedFileData($page, ["image"], ["small", "large"]);

        return new JsonResponse($projects);
    }
}
```

before hydration

```json
{
  "id": 12,
  "title": "My post",
  "status": "published",
  "content": "Content",
  "image": "page/beach",
  "createdAt": "2021-05-01T00:00:00+02:00",
  "website": "..."
}
```

after hydration

```json
{
  "id": 12,
  "title": "My post",
  "status": "published",
  "content": "Content",
  "image": {
    "original": "http://my-domain.com/uploads/page/beach.jpg",
    "small": "http://my-domain.com/media/cache/small/uploads/page/beach.jpg",
    "large": "http://my-domain.com/media/cache/large/uploads/page/beach.jpg"
  },
  "createdAt": "2021-05-01T00:00:00+02:00",
  "website": "..."
}
```

## with Mini File Manager JS library.

this bundle has been designed to integrate perfectly with [Mini File Manager](https://github.com/lhapaipai/mini-file-manager).

He offers `/media-manager` endpoint for the backend integration.

```console
npm i mini-file-manager
```

### without helpers

```twig
<head>
  <link rel="stylesheet" href="/dist/style.css" />
  <script src="/dist/mini-file-manager.umd.js"></script>
</head>
<body>
  <div id="file-manager"></div>

  <script>
    let config = {
      "endPoint": "/media-manager",
      "isAdmin": true,
      "fileValidation": [],
      "entryPoints": [
        {
          "directory": "",
          "origin": "public_uploads",
          "readOnly": false,
          "icon": "fa-lock",
          "label": "Uploads"
        }
      ]
    };

    new miniFileManager.createFileManager("#file-manager", config);
  </script>
</body>
```

### with helpers

```php
use Pentatrion\UploadBundle\Service\FileManagerHelper;

class ManagerController extends AbstractController
{
    #[Route('/manager', name: 'manager')]
    public function index(FileManagerHelper $fileManagerHelper): Response
    {
        $config = $fileManagerHelper->completeConfig([
            'isAdmin' => true,
            'entryPoints' => [
                [
                    'label' => 'Uploads',
                    'directory' => '',
                    'origin' => 'public_uploads',
                    'readOnly' => false,
                    'icon' => 'fa-lock'
                ]
            ]
        ]);
        return $this->render('manager/index.html.twig', [
            'fileManagerConfig' => $config,
        ]);
    }
}
```

Twig template for the file manager.
the mini-file-manager config is placed in data-props attribute.

```twig
<head>
  <link rel="stylesheet" href="/dist/style.css" />
  <script src="/dist/mini-file-manager.umd.js"></script>
</head>
<body>
  <div id="file-manager" data-props="{{ fileManagerConfig | json_encode | e('html_attr') }}"></div>

  <script>
    new miniFileManager.FileManager("#file-manager");
  </script>
</body>
```

Twig template for the file picker.

```twig

<head>
  <link rel="stylesheet" href="/dist/style.css" />
  <script src="/dist/mini-file-manager.umd.js"></script>
</head>
<body>
  <button id="find-file" data-props="{{ fileManagerConfig | json_encode | e('html_attr') }}">Find</button>

  <script>
    let findBtn = document.getElementById("find-file");
    findBtn.addEventListener("click", () => {
      let options = JSON.parse(findBtn.dataset.props);
      new miniFileManager.FileManagerModal(
        options,
        (files) => { console.log("onSuccess", files); },
        () => { console.log("onAbort"); }
      );
    });
  </script>
</body>
```

If you want more details about configuration, check [Mini File Manager](https://github.com/lhapaipai/mini-file-manager).

If you want exemple, check [Mini File Manager Template](https://github.com/lhapaipai/mini-file-manager-template)

## FilePickerType with mini-file-manager for your form

```php
use Pentatrion\UploadBundle\Form\FilePickerType;

class AdminUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('avatar', FilePickerType::class, [
              'fileManagerConfig' => [
                'entryPoints' => [
                  [
                    'label' => 'Uploads',
                    'directory' => '',
                    'origin' => 'public_uploads',
                    'readOnly' => false,
                    'icon' => 'fa-lock'
                  ]
                ],
                'fileValidation' => null,
                'fileUpload' => [
                  'maxFileSize' => 10 * 1024 * 1024,
                  'fileType' => [
                    "text/*",
                    "image/*", // image/vnd.adobe.photoshop  image/x-xcf
                    "video/*",
                    "audio/*"
                  ]
                ],
                'locale' => 'fr',
              ],
              'formPreviewConfig' => [
                'multiple'      => false,
                'filter'        => 'small',
                'type'          => 'image'
              ]
            )
        ;
    }
}
```

for full-list of imageOptions, have a look into [mini-file-manager#Configuration](https://github.com/lhapaipai/mini-file-manager#configuration) repository.

add custom form theme for your form builder :

```yaml
# config/packages/twig.yaml
twig:
  default_path: "%kernel.project_dir%/templates"
  form_themes: ["_form_theme.html.twig"]
```

```twig
{# templates/_form_theme.html.twig #}
{%- block file_picker_widget -%}
    {%- set attr = attr|merge({
        class: (attr.class|default('') ~ ' file-picker')|trim,
        'data-files': files,
        'data-minifilemanager': filemanager_config,
        'data-formpreview': formpreview_config
    }) -%}
    {{- block('form_widget_simple') -}}
{%- endblock -%}
```

import `assets/form-file-picker.js` from `pentatrion/upload-bundle` in your js.

## Advanced Bundle Configuration

configure your upload directories

### package configuration

```yaml
# config/packages/pentatrion_upload.yaml
pentatrion_upload:
  # Advanced config
  # must implement UploadedFileHelperInterface
  file_infos_helper: 'App\Service\AppUploadedFileHelper'

  origins:
    # choose the name of your choice
    public_uploads:
      # if directory is inside %kernel.project_dir%/public, files
      # will be directly accessible.
      path: "%kernel.project_dir%/public/uploads"
      # prefix to add in order to be found by a liip_imagine loader
      liip_path: "/uploads"
    private_uploads:
      path: "%kernel.project_dir%/var/uploads"
      liip_path: ""

  # if multiple origins
  default_origin: "public_uploads"

  # when you get infos from uploaded file put filters (liip_imagine.filter_sets)
  # you want the url used with mini-file-manager, put "small" to get thumbnails
  liip_filters: ["small", "large"]
```

if you set your class who implement UploadedFileHelperInterface (`file_infos_helper` option), you can extends UploadedFileHelper base class.

```php
<?php
namespace App\Service;
use Pentatrion\UploadBundle\Service\UploadedFileHelper;

class AppUploadedFileHelper extends UploadedFileHelper
{
}

```

You have to add 3 binding for your constructor

```yaml
#config/services.yaml
services:
  # default configuration for services in *this* file
  _defaults:
    autowire: true # Automatically injects dependencies in your services.
    autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.
    bind:
      $liipFilters: "%pentatrion_upload.liip_filters%"
      $defaultOriginName: "%pentatrion_upload.default_origin%"
      $uploadOrigins: "%pentatrion_upload.origins%"
```

## Manual installation

```console
composer require pentatrion/vite-bundle
```

if you do not want to use the recipe or want to see in depth what is modified by it.

add upload routes to your Symfony app.

```yaml
# config/routes/pentatrion_upload.yaml

# routes starting with /media-manager
_pentatrion_upload:
  prefix: /media-manager
  resource: "@PentatrionUploadBundle/Resources/config/routing.yaml"
```

add a config file for your bundle

```yaml
# config/packages/pentatrion_upload.yaml
# default configuration
pentatrion_upload:
  file_infos_helper: 'Pentatrion\UploadBundle\Service\UploadedFileHelper'
  origins:
    public_uploads:
      path: "%kernel.project_dir%/public/uploads"
      liip_path: "/uploads"

  # update to ["small"] if you have installed liip/imagine-bundle
  liip_filters: []
```

if you have installed liip/imagine-bundle add the small filter.

```yaml
# config/packages/liip_imagine.yaml
liip_imagine:
  driver: "gd"

  # define filters defined in pentatrion_upload.liip_filters
  # (at least small filter)
  filter_sets:
    small:
      filters:
        thumbnail: { size: [250, 250], mode: inset, allow_upscale: true }

  loaders:
    default:
      filesystem:
        data_root:
          # must be linked with pentatrion_upload -> origin.[origin-name].liip_path
          - "%kernel.project_dir%/public"
```
