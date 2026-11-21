<?php

namespace App\Http\Controllers\Admin;

ini_set('post_max_size','1024M');
ini_set('upload_max_filesize','1024M');

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Complex\Exception;
use Illuminate\Http\Request;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use App\CentralLogics\FileManagerLogic;
use Brian2694\Toastr\Facades\Toastr;
use Madnest\Madzipper\Facades\Madzipper;
use ZipArchive;

class FileManagerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($folder_path = "cHVibGlj", $storage = 'local')
    {

        if ($storage == 's3' && Helpers::getDisk()=='s3'){
            try {
                Storage::disk('s3')->exists($folder_path);
            } catch (\Exception $e){
                Toastr::error(translate('messages.something_went_wrong'));
                return back();
            }

            // The default token decodes to the bucket root, which is an
            // intentionally empty relative path.
            $decoded = $folder_path == "cHVibGlj" ? '' : $this->safePath(base64_decode($folder_path));
            if ($decoded === null) {
                Toastr::error('Invalid folder.');
                return back();
            }
            $directory = $decoded === '' ? '' : $decoded.'/';
            $s3 = Storage::disk('s3');
            $file = $directory === '' ? [] : $s3->allFiles($directory);
            $directories = $s3->allDirectories($directory);
        }else{
            $storage = 'local';
            $decoded = $this->safePath(base64_decode($folder_path));
            if ($decoded === null) {
                Toastr::error('Invalid folder.');
                return back();
            }
            $file = Storage::files($decoded);
            $directories = Storage::directories($decoded);

        }
        $folders = FileManagerLogic::format_file_and_folders($directories, 'folder');
        $files = FileManagerLogic::format_file_and_folders($file, 'file');


        $data = array_merge($folders, $files);

        return view('admin-views.file-manager.index', compact('data', 'folder_path','storage'));
    }


    public function upload(Request $request)
    {
        $request->validate([
            'images' => 'required_without:file',
            'file' => 'required_without:images',
            'path' => 'required_if:disk,local',
        ]);

        $disk = $request->disk === 's3' ? 's3' : 'local';
        if ($disk === 's3' && Helpers::getDisk() !== 's3') {
            Toastr::error(translate('messages.something_went_wrong'));
            return back();
        }
        $path = $this->safePath($request->path);
        if ($path === null) {
            Toastr::error('Invalid upload destination.');
            return back();
        }

        if ($request->hasfile('images')) {
            foreach ($request->file('images') as $image) {
                // Validate the bytes and derive a fixed, non-executable
                // extension. Client filenames and declared MIME are ignored.
                $extension = Helpers::validatedUploadExtension($image);
                $name = Carbon::now()->toDateString() . '-' . uniqid() . '.' . $extension;
                Storage::disk($disk)->put($path . '/' . $name, file_get_contents($image->getRealPath()));
            }
        }

        if ($request->hasfile('file')) {
            $this->extractArchiveSafely($request->file('file'), $path, $disk);
        }

        Toastr::success(translate('messages.image_uploaded_successfully'));
        return back()->with('success', translate('messages.image_uploaded_successfully'));
    }

    /**
     * Extract only safe, non-executable files from an uploaded archive. Archive
     * entry names are attacker-controlled: path components are canonicalised
     * and every extracted file is re-detected from its bytes and renamed.
     */
    private function extractArchiveSafely($archive, string $path, string $disk): void
    {
        $mime = strtolower((string) $archive->getMimeType());
        if (! in_array($mime, ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['Only zip archives are supported.'],
            ]);
        }

        $zip = new ZipArchive();
        if ($zip->open($archive->getRealPath()) !== true) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['The archive could not be opened.'],
            ]);
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! $stat || empty($stat['name']) || $this->shouldSkip($stat['name'])) {
                    continue;
                }
                $entry = str_replace('\\', '/', $stat['name']);
                if (str_contains($entry, '..') || str_starts_with($entry, '/') || preg_match('#^[A-Za-z]:#', $entry)) {
                    continue;
                }
                $contents = $zip->getFromIndex($i);
                if ($contents === false || $contents === '' || strlen($contents) > Helpers::uploadMaxBytes()) {
                    continue;
                }
                $extension = Helpers::safeUploadExtensionForMime(Helpers::mimeFromBytes($contents));
                if ($extension === null) {
                    continue;
                }
                $name = Carbon::now()->toDateString() . '-' . uniqid() . '.' . $extension;
                Storage::disk($disk)->put($path . '/' . $name, $contents);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Validate a user-supplied relative storage path. Rejects traversal,
     * absolute paths, NUL bytes and anything outside a simple relative form.
     */
    private function safePath($path): ?string
    {
        $path = (string) $path;
        if ($path === '') {
            return null;
        }
        if (str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/')) {
            return null;
        }
        $segments = array_values(array_filter(explode('/', $path), fn ($p) => $p !== '' && $p !== '.'));
        if (empty($segments)) {
            return null;
        }

        return implode('/', $segments);
    }

    private function shouldSkip($filename) {
        // Add conditions to skip files here
        $skipFiles = [
            '__MACOSX/', // Skip macOS metadata files
            '.DS_Store', // Skip .DS_Store files
            'Thumbs.db', // Skip Thumbs.db files (Windows)
            // Add more conditions as needed
        ];

        foreach ($skipFiles as $skipFile) {
            if (strpos($filename, $skipFile) === 0) {
                return true;
            }
        }

        return false;
    }


    public function download($file_name,$storage='public')
    {
        $decodedFileName = $this->safePath(base64_decode($file_name));
        if ($decodedFileName === null) {
            return false;
        }
        $storage = in_array($storage, ['public', 'local', 's3'], true) ? $storage : 'public';
        if (Storage::disk($storage)->exists($decodedFileName)) {
            return Storage::disk($storage)->download($decodedFileName);
        } elseif(Storage::disk('local')->exists($decodedFileName)){
            return Storage::disk('local')->download($decodedFileName);
        }
        return false;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }


    public function destroy($file_path)
    {
        $decoded = $this->safePath(base64_decode($file_path));
        if ($decoded === null) {
            Toastr::error('Invalid file path.');
            return back();
        }
        try {
            Storage::disk('local')->delete($decoded);
            Storage::disk('s3')->delete($decoded);
        } catch (\Exception $e){

        }
        Toastr::success(translate('messages.image_deleted_successfully'));
        return back()->with('success', translate('messages.image_deleted_successfully'));
    }
}
