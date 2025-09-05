<?php

namespace Dizzi\Services;

require __DIR__ . "/../../vendor/autoload.php";

use Dizzi\Config\Config;
use Aws\S3\S3Client;

class ImageService
{

    public static function upload(Config $env, string $fileKey = 'image'): ?string
    {

        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => $env->awsRegion, // região do seu Space
            'endpoint'    => 'https://nyc3.digitaloceanspaces.com',
            'credentials' => [
                'key'     => $env->awsKey,
                'secret'  => $env->awsSecret,
            ],
        ]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES[$fileKey])) {
            $file = $_FILES[$fileKey]['tmp_name'];
            $fileName = self::generateUniqueFileName(basename($_FILES[$fileKey]['name']));

            try {
                $s3->putObject([
                    'Bucket'     =>    $env->awsBucket, // nome do seu Space
                    'Key'        =>    'avatar/' . $fileName, // caminho dentro do Space
                    'SourceFile' =>    $file,
                    'ACL'        =>    'public-read', // ou 'private'
                ]);

                return "https://{$env->awsBucket}.nyc3.digitaloceanspaces.com/avatar/$fileName";
            } catch (\Exception $e) {
                error_log("Erro ao enviar para o S3 | imagem: " . $_FILES[$fileKey]['name'] . " | " . $e->getMessage());
                return null;
            }
        } else {
            throw new \Exception("Método inválido ou arquivo não enviado.");
        }
    }

    /**
     * Gera um nome único para arquivo mantendo a extensão original
     *
     * @param string $originalName Nome original do arquivo (ex: foto.png)
     * @return string Nome único (ex: 20250830_041530_5f7e3c8f9a.png)
     */
    public static function generateUniqueFileName(string $originalName): string
    {
        // Extrai a extensão do arquivo
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);

        // Gera prefixo com data/hora + microsegundos
        $prefix = date('Ymd_His') . '_' . substr(uniqid(), -8);

        // Retorna nome completo
        return $prefix . ($ext ? '.' . $ext : '');
    }


    public static function checkMimeType()
    {
        echo "foo";
    }

    public static function compress() {}
}
