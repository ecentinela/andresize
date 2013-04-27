<?php

// autoloader
require_once __DIR__.'/../vendor/autoload.php';

// generate app
$app = new Silex\Application();

// enable debug
$app['debug'] = false;

// register whoops
if ($app['debug']) {
    $app->register(new Whoops\Provider\Silex\WhoopsServiceProvider);
}

// register twig
$app->register(
    new Silex\Provider\TwigServiceProvider(),
    array(
        'twig.path'    => __DIR__.'/views',
        'twig.options' => array(
            'cache' => __DIR__.'/../cache'
        )
    )
);

// register translation
$app->register(new Silex\Provider\TranslationServiceProvider());

// set translations
$app['translator.domains'] = array(
    'messages' => array(
        'en' => array(
            'title'              => 'Image Resizer for Android',
            'resize_your_images' => 'Resize the images',
            'upload_a_file'      => 'Upload or drop a zip file containing the images <b>xhdpi</b> of your Android project.',
            'wait_for_downloads' => 'After some seconds, a link with a download for <b>hdpi</b>, <b>mdpi</b> and <b>ldpi</b> versions will appear.',
            'select_zip_file'    => 'Click here to select a <b>ZIP</b> file'
        ),
        'es' => array(
            'title'              => 'Image Resizer for Android',
            'resize_your_images' => 'Redimensiona las imágenes',
            'upload_a_file'      => 'Sube o arrastra un archivo zip que contenga las imágenes <b>xhdpi</b> de tu proyecto Android.',
            'wait_for_downloads' => 'Después de unos segundos, aparecerán los enlaces de descarga para las versiones <b>hdpi</b>, <b>mdpi</b> y <b>ldpi</b>.',
            'select_zip_file'    => 'Pincha aqui para seleccionar el archivo <b>ZIP</b>'
        )
    )
);

// register assetic
$app->register(
    new SilexAssetic\AsseticServiceProvider(),
    array(
        'assetic.path_to_web' => __DIR__.'/../web',
        'assetic.options'     => array(
            'auto_dump_assets' => $app['debug'],
            'debug'            => $app['debug']
        )
    )
);

$closure = new Assetic\Filter\GoogleClosure\CompilerApiFilter();

$app['assetic.filter_manager']->set('closure', $closure);

$stylus = new Assetic\Filter\StylusFilter(
    '/Users/javier/.nvm/v0.8.22/bin/node',
    array('/Users/javier/.nvm/v0.8.22/lib/node_modules')
);

$stylus->setCompress(true);

$app['assetic.filter_manager']->set('stylus', $stylus);

$less = new Assetic\Filter\LessFilter(
    '/Users/javier/.nvm/v0.8.22/bin/node',
    array('/Users/javier/.nvm/v0.8.22/lib/node_modules')
);

$less->setCompress(true);

$app['assetic.filter_manager']->set('less', $less);

$coffee = new Assetic\Filter\CoffeeScriptFilter(
    '/Users/javier/.nvm/v0.8.22/bin/coffee',
    '/Users/javier/.nvm/v0.8.22/bin/node'
);

$app['assetic.filter_manager']->set('coffee', $coffee);

// set routing
$app->get(
    '/',
    function (Symfony\Component\HttpFoundation\Request $request) use ($app) {
        // redirect to preferred language
        $locale = substr($request->getPreferredLanguage(), 0, 2);

        // check language is valid
        $valids = array_keys($app['translator.domains']['messages']);

        if (!array_key_exists($locale, $valids)) {
            $locale = 'es';
        }

        // redirect to valid language
        return $app->redirect("/$locale");
    }
);

$app->get(
    '/{_locale}',
    function ($_locale) use ($app) {
        return $app['twig']->render(
            'index.twig',
            array(
                'lang'   => $_locale == 'es' ? 'es_ES' : 'en_US',
                'locale' => $_locale,
                'hash'   => time().'_'.base_convert(sha1(uniqid(mt_rand(), true)), 16, 36)
            )
        );
    }
);

$app->post(
    '/upload/{hash}',
    function ($hash, Symfony\Component\HttpFoundation\Request $request) use ($app) {
        // time limti for 5 minutes
        set_time_limit(5 * 60);

        // get uploaded file
        $file = $request->files->get('file');

        // check it's a zip file
        if ($file->getMimeType() != 'application/zip') {
            return $app->abort(500, 'invalid file type '.$file->getMimeType());
        }

        // move to the download folder
        $file->move(__DIR__.'/../web/downloads', $hash.'.zip');

        // uncompress file
        $dir = __DIR__.'/../web/downloads/'.$hash;

        file_exists($dir) || mkdir($dir);

        $zip = new ZipArchive();

        $zip->open($dir.'.zip');

        $zip->extractTo($dir);

        $zip->close();

        // get images
        $images = Symfony\Component\Finder\Finder::create()
            ->in($dir)
            ->name('/\.(gif|jpg|jpeg|png)/');

        // create imagine
        $imagine = new Imagine\Imagick\Imagine();

        // function to generate images
        $generate = function ($version) use ($app, $hash, $images, $imagine) {
            // directory where to generate the files
            $dir = __DIR__.'/../web/downloads/'.$hash.'_'.$version;

            // create the directory
            file_exists($dir) || mkdir($dir);

            // create the zip
            $zip = new ZipArchive();

            $zip->open($dir.'.zip', ZIPARCHIVE::CREATE);

            // size multiplier
            switch ($version) {
                case 'xhdpi':
                    $multiplier = 1;
                    break;
                case 'hdpi':
                    $multiplier = 0.75;
                    break;
                case 'mdpi':
                    $multiplier = 0.5;
                    break;
                case 'ldpi':
                    $multiplier = 0.375;
                    break;
            }

            // resize
            foreach ($images as $image) {
                // open image
                $img = $imagine->open(
                    $image->getPathname()
                );

                // get actual size
                $size = $img->getSize();

                // where to save the file
                $file = $dir.'/'.$image->getFilename();

                // save resized version
                $img
                    ->resize(
                        new Imagine\Image\Box(
                            $size->getWidth() * $multiplier,
                            $size->getHeight() * $multiplier
                        )
                    )
                    ->save($file);

                // add it to the zip
                $zip->addFile($file, '/drawable-'.$version.'/'.$image->getFilename());
            }

            // close zip
            $zip->close();
        };

        // xhdpi
        $generate('xhdpi');

        // hdpi
        $generate('hdpi');

        // mdpi
        $generate('mdpi');

        // ldpi
        $generate('ldpi');

        // return empty response
        return '';
    }
);

$app->get(
    '/status/{hash}',
    function ($hash) use ($app) {
        return $app->json(
            array(
                'xhdpi' => file_exists(__DIR__.'/../web/downloads/'.$hash.'_xhdpi.zip'),
                'hdpi'  => file_exists(__DIR__.'/../web/downloads/'.$hash.'_hdpi.zip'),
                'mdpi'  => file_exists(__DIR__.'/../web/downloads/'.$hash.'_mdpi.zip'),
                'ldpi'  => file_exists(__DIR__.'/../web/downloads/'.$hash.'_ldpi.zip')
            )
        );
    }
);

// return app
return $app;
