<?php

namespace App\ImportExport;

use App\Runtime\RuntimeHttpException;

final class ImportExportEncodingHelper
{
    public function normalizeEncodingLabel(mixed $value): string
    {
        $encoding = trim((string) $value);
        if ($encoding === '') {
            return 'UTF-8';
        }

        foreach (mb_list_encodings() as $supported) {
            if (strcasecmp($supported, $encoding) === 0) {
                return $supported;
            }
        }

        throw new RuntimeHttpException('IMPORT_EXPORT_ENCODING_INVALID', 'Encoding informado nao e suportado.', 422, [
            'encodingLabel' => $encoding,
        ]);
    }

    public function encodeOutput(string $value, string $encoding): string
    {
        if (strcasecmp($encoding, 'UTF-8') === 0) {
            return $value;
        }

        return mb_convert_encoding($value, $encoding, 'UTF-8');
    }

    public function decodeOutput(string $value, string $encoding): string
    {
        if (strcasecmp($encoding, 'UTF-8') === 0) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', $encoding);
    }
}
