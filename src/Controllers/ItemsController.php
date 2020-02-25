<?php

namespace UniSharp\LaravelFilemanager\Controllers;

use UniSharp\LaravelFilemanager\Events\FileIsMoving;
use UniSharp\LaravelFilemanager\Events\FileWasMoving;
use UniSharp\LaravelFilemanager\Events\FolderIsMoving;
use UniSharp\LaravelFilemanager\Events\FolderWasMoving;

class ItemsController extends LfmController
{
    private $columns = [ 'name', 'time', 'size', 'readable_size'];
    /**
     * Get the images to load for a selected folder.
     *
     * @return mixed
     */
    public function getItems()
    {
        // $sort_type = $this->helper->input('sort_type');
        // $limit = $this->helper->input('limit');
        // $offset = $this->helper->input('offset');
        // $limit = $limit > 0 ? $limit : 10;
        // $offset = $offset > 0 ? $offset : 0;
        $files = $this->lfm->files($this->lfm->path('storage'));//, $limit, $offset);
        $folders = $this->lfm->folders();
        $folders = array_map(function($folder) {
            return $this->lfm->pretty($folder->path);
        },$folders);
        // $is_last_page = ($offset + $limit) > count($files);
        // $is_first_page = $offset === 0;
        $items = collect(array_merge($folders,$files))->map(function ($item) {
            return $item->fill()->attributes;
        })->toArray();
        return [
            // 'limit'     => $limit,
            // 'offset'     => $offset,
            // 'files_count'     => count($files),
            // 'is_first_page' => $is_first_page,
            // 'is_last_page' => $is_last_page,
            'items' => $items,
            'columns' => $this->columns,
            'display' => $this->helper->getDisplayMode(),
            'working_dir' => $this->lfm->path('working_dir'),
        ];
    }

    public function move()
    {
        $items = request('items');
        $folder_types = array_filter(['user', 'share'], function ($type) {
            return $this->helper->allowFolderType($type);
        });
        return view('laravel-filemanager::move')
            ->with([
                'root_folders' => array_map(function ($type) use ($folder_types) {
                    $path = $this->lfm->dir($this->helper->getRootFolder($type));

                    return (object) [
                        'name' => trans('laravel-filemanager::lfm.title-' . $type),
                        'url' => $path->path('working_dir'),
                        'children' => $path->folders(),
                        'has_next' => ! ($type == end($folder_types)),
                    ];
                }, $folder_types),
            ])
            ->with('items', $items);
    }

    public function domove()
    {
        $target = $this->helper->input('goToFolder');
        $items = $this->helper->input('items');

        foreach ($items as $item) {
            $old_file = $this->lfm->pretty($item);
            $is_directory = $old_file->isDirectory();

            if ($old_file->hasThumb()) {
                $new_file = $this->lfm->setName($item)->thumb()->dir($target);
                if ($is_directory) {
                    event(new FolderIsMoving($old_file->path(), $new_file->path()));
                } else {
                    event(new FileIsMoving($old_file->path(), $new_file->path()));
                }
                $this->lfm->setName($item)->thumb()->move($new_file);
            }
            $new_file = $this->lfm->setName($item)->dir($target);
            $this->lfm->setName($item)->move($new_file);
            if ($is_directory) {
                event(new FolderWasMoving($old_file->path(), $new_file->path()));
            } else {
                event(new FileWasMoving($old_file->path(), $new_file->path()));
            }
        };

        return parent::$success_response;
    }
}
