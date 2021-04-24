pentatrion/upload-bundle

Work In Progress !

Provide Upload Helper and endpoints for a File Manager in your Symfony Application

## Description

dependances:

- liip/imagine-bundle

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
# config/packages/pentatrion_upload.yaml
pentatrion_upload:
  file_infos_helper: 'App\Service\SuiviFileInfosHelper'

  origins:
    # choose the name of your choice
    public:
      # if directory is inside %kernel.project_dir%/public, files
      # will be directly accessible.
      path: "%kernel.project_dir%/public/uploads"

    private:
      path: "%kernel.project_dir%/var/uploads"
```

configure liip loaders

```yaml
# See dos how to configure the bundle: https://symfony.com/doc/current/bundles/LiipImagineBundle/basic-usage.html
liip_imagine:
  # valid drivers options include "gd" or "gmagick" or "imagick"
  driver: "gd"

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
          public: "%kernel.project_dir%/public"
          private: "%kernel.project_dir%/var/uploads"
```

## Utilisation

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
                $fileInfos = $fileHelper->uploadFile(
                    $file,
                    'posts', // relative path
                    'public', // origin
                    [ // default options
                        // 'forceFilename' => 'logo',
                        'urlize' => true,
                        'prefix' => '',
                        'guessExtension' => false,
                        'unique' => false
                    ]
                );
                $post->setImage($fileInfos['filename']);
            }

            $this->getDoctrine()->getManager()->flush();

            // ...
        }
        // ...
    }
}
```

TODO Uploader

```php
dump($fileInfos);
[
  "inode"       => 8653642
  "id"          => "@public:uploads/posts/logo.svg"
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
