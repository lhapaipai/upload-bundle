Provide Upload Helper and endpoints for a File Manager in your Symfony Application

## Description

2 twig functions

```twig

<!-- /uploads/folder/my-uploaded-file.pdf -->
{{ uploaded_file_web_path("folder/my-uploaded-file.pdf") }}

<!-- /media-manager/get/show/private_uploads/folder/my-uploaded-file.pdf -->
{{ uploaded_file_web_path("folder/my-uploaded-file.pdf", "private_uploads") }}

<!-- http://localhost/media/cache/resolve/small/posts/logo.jpg -->
{{ uploaded_image_filtered('posts/logo.jpg', 'small') }}

<!-- http://localhost/media/cache/resolve/small/posts/logo.jpg -->
{{ uploaded_image_filtered('posts/logo.jpg', 'small', 'private_uploads') }}
```

dependances:

- liip/imagine-bundle
- symfony/validator

## installation

```bash
composer require pentatrion/upload-bundle
```

add upload routes to your Symfony app

```yaml
# config/routes/pentatrion_upload.yaml
_pentatrion_upload:
  resource: "@PentatrionUploadBundle/Controller/UploadController.php"
  type: annotation
```

configure your upload directories

```yaml
## config/packages/pentatrion_upload.yaml
## default config
# pentatrion_upload:
#   file_infos_helper: 'Pentatrion\UploadBundle\Service\FileInfosHelper'
#   origins:
#     public_uploads:
#       path: "%kernel.project_dir%/public/uploads"
#       liip_path: "/uploads"
#   liip_filters: ["small", "large"]

pentatrion_upload:
  # must implement FileInfosHelperInterface
  file_infos_helper: 'App\Service\AppFileInfosHelper'

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

  liip_filters: ["small", "large"]
```

configure liip loaders

```yaml
liip_imagine:
  # valid drivers options include "gd" or "gmagick" or "imagick"
  driver: "gd"

  # define filters defined in pentatrion_upload.liip_filters
  filter_sets:
    small:
      filters:
        thumbnail: { size: [250, 250], mode: inset, allow_upscale: true }

    large:
      filters:
        thumbnail: { size: [1500, 1500], mode: inset, allow_upscale: false }

  loaders:
    default:
      filesystem:
        data_root:
          # must be linked with pentatrion_upload -> liip_path
          - "%kernel.project_dir%/public"
          - "%kernel.project_dir%/var/uploads"
```

## Simple Utilisation

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

## Utilisation

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
<img src="{{ uploaded_file_web_path(page.image) }}" />
<img src="{{ uploaded_image_filtered(page.image, 'small') }}" />
```

## For your API Calls

```php
#[Route('/api', name: 'api_', defaults:["_format"=>"json"])]
class ApiController extends AbstractController
{

    #[Route('/page/{id}', name: 'projects')]
    public function showProjects(Page $page, FileInfosHelper $fileInfosHelper): Response
    {
        // hydrate image field, with original/small/large webpaths.
        $page = $fileInfosHelper->hydrateEntityWithUploadedFileData($page, ["image"], ["small", "large"]);

        return new JsonResponse($projects);
    }
}
```

return

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

## with FormType

```php
use Pentatrion\UploadBundle\Form\FilePickerType;

class AdminUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('avatar', FilePickerType::class, [
                'required' => false,
                'uploadDirectory' => 'profile',
                'uploadOrigin' => 'public_uploads',

                // must be any key defined in
                // config/packages/pentatrion_upload.yaml -> liip_filters
                'previewFilter' => 'small',
                'previewClass' => 'rounded-corner',

                // file or image
                'previewType' => 'image',

                'fileValidation' => [
                    'mimeGroup' => 'image',
                    'imageOptions' => [
                        'ratio' => 1,
                        'minWidth' => 300
                    ]
                ]
            ])
        ;
    }
}
```

for full-list of imageOptions, have a look into [mini-file-manager#Configuration](https://github.com/lhapaipai/mini-file-manager#configuration) repository.

add custom form theme for your form builder :

```twig
{%- block file_picker_widget -%}
    {{- block('form_widget_simple') -}}
    {% if preview.type == 'image' %}
        <div class="preview image">
            <img data-type="image" class="{{ preview.class }}" src="{{ uploaded_image_filtered(preview.path, preview.filter, preview.origin) }}" data-filter="{{ preview.filter }}" alt="">
            <div class="filename">{{ preview.filename }}</div>
            <div class="actions">
                <a href="#" class="remove"><i class="fa-trash"></i></a>
                <a href="#" class="browse" data-target="{{ id }}" data-filemanager="{{ picker_config | e('html_attr') }}"><i class="fa-folder"></i></a>
            </div>
        </div>
    {% else %}
        <div class="preview file">
            <img data-type="file" src="{{ preview.path }}" alt="">
            <div class="filename">{{ preview.filename }}</div>
            <div class="actions">
                <a href="#" class="remove"><i class="fa-trash"></i></a>
                <a href="#" class="browse" data-target="{{ id }}" data-filemanager="{{ picker_config | e('html_attr') }}"><i class="fa-folder"></i></a>
            </div>
        </div>
    {% endif %}
    <button class="btn outlined browse" data-target="{{ id }}" data-filemanager="{{ picker_config | e('html_attr') }}">Parcourir</button>

{%- endblock -%}
```

import `assets/file-picker.js` and `assets/file-picker.scss` in your js.

## More Details

```php
// more customization
$fileInfos = $fileHelper->uploadFile(
    $file,
    'posts', // subDirectory
    'public_uploads', // origin
    [
        'forceFilename' => 'logo',
        'urlize' => true,
        'prefix' => '',
        'guessExtension' => false,
        'unique' => false
    ]
);
```

TODO Uploader

```php
dump($fileInfos);
[
  "inode"       => 8653642
  "id"          => "@public_uploads:uploads/posts/logo.svg"
  "filename"    => "logo.svg"
  "directory"   => "posts"
  "uploadRelativePath" => "posts/logo.svg"
  "mimeType"    => "image/svg"
  "type"        => "file"
  "uploader"    => "Hugues"
  "origin"      => "public"
  "size"        => 3021
  "humanSize"   => "3.0 Ko"
  "createdAt"   => DateTime
  "isDir"       => false
  "url"         => "http://localhost/uploads/posts/logo.svg"
  "icon"        => "/images/icons/image-svg+xml.svg"
  "thumbnails"  => [
    "small"     => "http://localhost/uploads/posts/logo.svg"
    "full"  => "http://localhost/uploads/posts/logo.svg"
  ]
]
```

verify to create directory with http user access
in origins path, liipImagineBundle cache path : `public/media`.
