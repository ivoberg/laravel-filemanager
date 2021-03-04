<?php

namespace UniSharp\LaravelFilemanager;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Models\Media;
use Storage;
use Carbon\Carbon;

class LfmItem
{
    private $attachableModuleTypes = [
        'CasinoGames' => 'casinoGames',
        'StaticPages' => 'staticPages',
        'Teasers' => 'teasers',
        'Carousels' => 'carousels'];
    private $lfm;
    private $helper;
    private $isDirectory;
    private $mimeType = null;

    private $columns = ['icon', 'name', 'title', 'key', 'time', 'is_folder', 'is_folder', 'is_file', 'is_image', 'url', 'thumb_url', 'folder_path', 'path', 'storage', 'extension', 'size', 'readable_size', 'type', 'pixel_size'];
    public $attributes = [];

    public function __construct(LfmPath $lfm, Lfm $helper, $isDirectory = false)
    {
        $this->lfm = $lfm->thumb(false);
        $this->helper = $helper;
        $this->isDirectory = $isDirectory;
        $this->columns = $helper->config('item_columns') ?? $this->columns;
    }

    public function __get($var_name)
    {
        if (!array_key_exists($var_name, $this->attributes)) {
            $function_name = Str::camel($var_name);
            $this->attributes[$var_name] = $this->$function_name();
        }

        return $this->attributes[$var_name];
    }

    public function fill()
    {
        foreach ($this->columns as $column) {
            $this->__get($column);
        }
        if (!$this->isDirectory() && config('lfm.medialibrary') === 'Spatie') {
            $this->__get('media');
            // $this->__get('modules');
        }


        return $this;
    }

    public function media()
    {
        if ($this->isDirectory()) {
            return false;
        }
        if (config('lfm.medialibrary') === 'Spatie' && config('lfm.mediamodel')) {
            $mediaClass = config('lfm.mediamodel');
        }

        $media = $mediaClass::where('file_name', $this->path('storage'))->first();
        if (!$media) {
            return null;
        }
        return $this->attributes['media'] = $media;
    }

    public function modules()
    {
        if ($this->isDirectory()) {
            return false;
        }
        if (config('lfm.medialibrary') === 'Spatie' && config('lfm.mediamodel')) {
            $mediaClass = config('lfm.mediamodel');
        }
        if($mediaClass)
            $media = $mediaClass::where('file_name', $this->path('storage'))->first();

        if (!$media) {
            return null;
        }
        foreach ($this->attachableModuleTypes as $key => $value) {
            $attachable = $media->modules($key)->get();
            $this->attributes['modules'][$value] = $attachable;
        }
        return $this->attributes['modules'];
    }

    public function key()
    {
        return $this->url(); //?: (string) Str::uuid();
    }

    public function name()
    {
        return $this->lfm->getName();
    }

    public function title()
    {
        return $this->lfm->getName();
    }

    public function path($type = 'absolute')
    {
        return $this->lfm->path($type);
    }

    public function storage($type = 'storage')
    {
        return $this->lfm->path($type);
    }

    public function folderPath()
    {
        return str_replace($this->name(), '', $this->path('working_dir'));
    }

    public function isDirectory()
    {
        return $this->isDirectory;
    }

    public function isFolder()
    {
        return $this->isDirectory();
    }

    public function isFile()
    {
        return !$this->isDirectory();
    }

    /**
     * Check a file is image or not.
     *
     * @param  mixed  $file  Real path of a file or instance of UploadedFile.
     * @return bool
     */
    public function isImage()
    {
        return $this->isFile() && Str::startsWith($this->mimeType(), 'image');
    }

    /**
     * Get mime type of a file.
     *
     * @param  mixed  $file  Real path of a file or instance of UploadedFile.
     * @return string
     */
    public function mimeType()
    {
        if (is_null($this->mimeType)) {
            $this->mimeType = $this->lfm->mimeType();
        }

        return $this->mimeType;
    }

    public function extension()
    {
        return $this->lfm->extension();
    }

    public function url()
    {
        if ($this->isDirectory()) {
            return $this->lfm->path('working_dir');
        }

        return $this->lfm->url();
    }

    public function pixelSize()
    {
        if (!$this->isImage()) {
            return false;
        }
        list($width, $height, $type, $attr) = getimagesize($this->path);
        return $width.'x'.$height;
    }

    public function size()
    {
        return $this->isFile() ? $this->lfm->size() : '';
    }

    public function readableSize()
    {
        return $this->isFile() ? $this->humanFilesize($this->lfm->size()) : '';
    }

    public function time()
    {

        if (!$this->isDirectory()) {
            return Carbon::parse($this->lfm->lastModified(), 'Europe/Berlin')->format('d.m.y H:i:s');
        }
        return false;

    }

    public function thumbUrl()
    {
        if ($this->isDirectory()) {
            return asset('vendor/' . Lfm::PACKAGE_NAME . '/img/folder.png');
        }

        if ($this->isImage()) {
            return $this->lfm->thumb($this->hasThumb())->url(true);
        }

        return null;
    }

    public function icon()
    {
        if ($this->isDirectory()) {
            return 'folder';
        }

        if ($this->isImage()) {
            return 'image';
        }

        return $this->extension();
    }

    public function type()
    {
        if ($this->isDirectory()) {
            return trans(Lfm::PACKAGE_NAME . '::lfm.type-folder');
        }

        if ($this->isImage()) {
            return $this->mimeType();
        }

        return $this->helper->getFileType($this->extension());
    }

    public function hasThumb()
    {
        if (!$this->isImage()) {
            return false;
        }

        if (!$this->lfm->thumb()->exists()) {
            return false;
        }

        return true;
    }

    public function shouldCreateThumb()
    {
        if (!$this->helper->config('should_create_thumbnails')) {
            return false;
        }

        if (!$this->isImage()) {
            return false;
        }

        if (in_array($this->mimeType(), ['image/gif', 'image/svg+xml'])) {
            return false;
        }

        return true;
    }

    public function get()
    {
        return $this->lfm->get();
    }

    /**
     * Make file size readable.
     *
     * @param  int  $bytes     File size in bytes.
     * @param  int  $decimals  Decimals.
     * @return string
     */
    public function humanFilesize($bytes, $decimals = 2)
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), @$size[$factor]);
    }
}
