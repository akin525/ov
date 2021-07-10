<?php

namespace StripeJS;

/**
 * Class FileUpload
 *
 * @property string $id
 * @property string $object
 * @property int $created
 * @property string $purpose
 * @property int $size
 * @property string $type
 *
 * @package StripeJS
 */
class FileUpload extends ApiResource
{
    public static function baseUrl()
    {
        return StripeJS::$apiUploadBase;
    }

    public static function className()
    {
        return 'file';
    }

    /**
     * @param array|string $id The ID of the file upload to retrieve, or an
     *     options array containing an `id key.
     * @param array|string|null $opts
     *
     * @return FileUpload
     */
    public static function retrieve($id, $opts = null)
    {
        return self::_retrieve($id, $opts);
    }

    /**
     * @param array|null $params
     * @param array|string|null $opts
     *
     * @return FileUpload The created file upload.
     */
    public static function create($params = null, $opts = null)
    {
        return self::_create($params, $opts);
    }

    /**
     * @param array|null $params
     * @param array|string|null $opts
     *
     * @return Collection of FileUploads
     */
    public static function all($params = null, $opts = null)
    {
        return self::_all($params, $opts);
    }
}
