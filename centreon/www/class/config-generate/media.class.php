<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 * Class
 *
 * @class Media
 */
class Media extends AbstractObject
{
    /** @var null */
    private $medias = null;

    /**
     * @throws PDOException
     * @return void
     */
    private function getMedias(): void
    {
        $query = 'SELECT img_id, img_name, img_path, dir_name FROM view_img, view_img_dir_relation, view_img_dir '
            . 'WHERE view_img.img_id = view_img_dir_relation.img_img_id '
            . 'AND view_img_dir_relation.dir_dir_parent_id = view_img_dir.dir_id';
        $stmt = $this->backend_instance->db->prepare($query);
        $stmt->execute();
        $this->medias = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

    /**
     * @param $media_id
     *
     * @throws PDOException
     * @return string|null
     */
    public function getMediaPathFromId($media_id)
    {
        if (is_null($this->medias)) {
            $this->getMedias();
        }

        $result = null;
        if (! is_null($media_id) && isset($this->medias[$media_id])) {
            $result = $this->medias[$media_id]['dir_name'] . '/' . $this->medias[$media_id]['img_path'];
        }

        return $result;
    }
}
