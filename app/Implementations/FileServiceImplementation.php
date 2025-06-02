<?php

namespace App\Implementations;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

class FileServiceImplementation implements FileService{
    public function uploadFichier(UploadedFile $fichier, String $chemin='storage/app', String $filename):File
    {
        return $fichier->move(
            base_path($chemin),
            $filename,
        );
    }

    public function readFile($filePath): File
    {
        return new File($filePath);
    }
}
