<?php

namespace UniSharp\LaravelFilemanager\Controllers;

use Illuminate\Support\Facades\Log;
use UniSharp\LaravelFilemanager\Events\ImageIsUploading;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use UniSharp\LaravelFilemanager\Events\ImageWasUploaded;
use UniSharp\LaravelFilemanager\Lfm;
use Storage;
class UploadController extends LfmController
{
    protected $errors;

    public function __construct()
    {
        parent::__construct();
        $this->errors = [];
    }

    /**
     * Upload files
     *
     * @param void
     * @return string
     */
    public function upload()
    {
        $uploaded_files = request()->file('upload');
        $error_bag = [];
        $new_filename = null;

        foreach (is_array($uploaded_files) ? $uploaded_files : [$uploaded_files] as $file) {
            try {
                $new_filename = $this->lfm->upload($file);
            } catch (\Exception $e) {
                Log::error($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                array_push($error_bag, $e->getMessage());
            }
        }

        if (is_array($uploaded_files)) {
            $response = count($error_bag) > 0 ? $error_bag : parent::$success_response;
        } else { // upload via ckeditor5 expects json responses
            if (is_null($new_filename)) {
                $response = ['error' =>
                                [
                                    'message' =>  $error_bag[0]
                                ]
                            ];
            } else {
                $url = $this->lfm->setName($new_filename)->url();

                $response = [
                    'url' => $url
                ];
            }
        }

        return response()->json($response);
    }

    public function uploadValidator()
    {
        try {
            $filename = request()->input('file');
            $working_dir = request()->input('working_dir');

            if (empty($filename)) {
                return $this->error('filename-empty');
            } elseif (!is_string($filename)) {
                return $this->error('not-string');
            }

            $exists = Storage::disk('public')->exists('uploads'.$working_dir.'/'.$filename);
            if(!$exists) return $this->error('file-empty');
            $file = new UploadedFile(
                storage_path('app/public/uploads'.$working_dir.'/'.$filename),
                $filename
            );

            $new_file_name = $this->lfm->getNewName($file);

            if ($this->lfm->setName($new_file_name)->exists()) {
                return response('already-exists');
            }
            if (config('lfm.should_validate_mime', false)) {
                $mimetype = $file->getMimeType();
                if (false === in_array($mimetype, $this->helper->availableMimeTypes())) {
                    return $this->error('mime') . $mimetype;
                }
            }

            if (config('lfm.should_validate_size', false)) {
                // size to kb unit is needed
                $file_size = $file->getSize() / 1000;
                if ($file_size > $this->helper->maxUploadSize()) {
                    return $this->error('size') . $file_size;
                }
            }
            return 'pass';
        } catch (\Exception $e) {
            Log::error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $e->getMessage();
        }

    }

}
