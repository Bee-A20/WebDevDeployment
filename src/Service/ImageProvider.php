<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ImageProvider
{
    private string $publicDir;

    public function __construct(ParameterBagInterface $params)
    {
        $this->publicDir = $params->get('kernel.project_dir') . '/public';
    }

    /**
     * Get all available images from the Shop directory
     * @return array<string, string> Array of image paths [filename => full path]
     */
    public function getAvailableImages(): array
    {
        $shopDir = $this->publicDir . '/Shop';
        $images = [];

        if (!is_dir($shopDir)) {
            return $images;
        }

        $files = scandir($shopDir);
        if ($files === false) {
            return $images;
        }

        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $filePath = $shopDir . '/' . $file;
            
            // Only include image files
            if (is_file($filePath) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
                $images['Shop/' . $file] = 'Shop/' . $file;
            }
        }

        ksort($images);
        return $images;
    }
}
