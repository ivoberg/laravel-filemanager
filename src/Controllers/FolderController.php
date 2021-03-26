<?php

namespace UniSharp\LaravelFilemanager\Controllers;

class FolderController extends LfmController
{
    /**
     * Get list of folders as json to populate treeview.
     *
     * @return mixed
     */
    public function getFoldersData()
    {
        $folder_types = array_filter(['share', 'user'], function ($type) {
            return $this->helper->allowFolderType($type);
        });

        $folders_data = [
                'root_folders' => array_map(function ($type) use ($folder_types) {
                    $path = $this->lfm->dir($this->helper->getRootFolder($type));
                    $url = $path->path('url');
                    return (object) [
                        'key' => $path->path('working_dir'),
                        'title' => trans('laravel-filemanager::lfm.title-' . $type),
                        'url' => $url,
                        'path' => $path->path('storage'),
                        'has_next' => ! ($type == end($folder_types)),
                        'children' => $this->getChildrenFoldersData($path),
                    ];
                }, $folder_types),
            ];
        return $folders_data;
    }
    /**
     * Get list of folders as json to populate treeview.
     *
     * @return mixed
     */
    public function getChildrenFoldersData($parent)
    {
        $folders = $parent->folders();
        return array_map(function ($childpath) {
            $path = $this->lfm->dir($childpath->path('working_dir'));
            $childpath = $childpath->fill()->attributes;
            $childpath['children'] = $this->getChildrenFoldersData($path);
            return $childpath;
        }, $folders);
    }
    /**
     * Get list of folders as json to populate treeview.
     *
     * @return mixed
     */
    public function getFolders()
    {
        return
        view('laravel-filemanager::tree')
            ->with($this->getFoldersData());
    }

    /**
     * Add a new folder.
     *
     * @return mixed
     */
    public function getAddfolder()
    {
        $folder_name = $this->helper->input('name');

        try {
            if (empty($folder_name)) {
                return $this->helper->error('folder-name');
            } elseif ($this->lfm->setName($folder_name)->exists()) {
                return $this->helper->error('folder-exist');
            } elseif (config('lfm.alphanumeric_directory') && preg_match('/[^\w-]/i', $folder_name)) {
                return $this->helper->error('folder-alnum');
            } else {
                $this->lfm->setName($folder_name)->createFolder();
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return parent::$success_response;
    }
}
