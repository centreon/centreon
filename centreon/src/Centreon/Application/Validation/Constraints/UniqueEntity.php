<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 *
 */

namespace Centreon\Application\Validation\Constraints;

use Centreon\Application\Validation\Validator\UniqueEntityValidator;
use Symfony\Component\Validator\Constraint;

class UniqueEntity extends Constraint
{
    public const NOT_UNIQUE_ERROR = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077c';

    /**
     * @var string
     */
    public string $validatorClass = UniqueEntityValidator::class;

    /**
     * @var string
     */
    public $message = 'This value is already used.';

    /**
     * @var string
     */
    public $entityIdentificatorMethod = 'getId';

    /**
     * @var string
     */
    public $entityIdentificatorColumn = 'id';

    /**
     * @var mixed
     */
    public $repository = null;

    /**
     * @var string
     */
    public $repositoryMethod = 'findOneBy';

    /**
     * @var array<mixed>
     */
    public $fields = [];

    /**
     * @var string|null
     */
    public $errorPath = null;

    /**
     * @var bool
     */
    public $ignoreNull = true;

    /**
     * @var array<string, string>
     */
    protected const ERROR_NAMES = [
        self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR',
    ];

    /**
     * {@inheritdoc}
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * @return string
     */
    public function getDefaultOption(): string
    {
        return 'fields';
    }

    /**
     * The validator class name.
     *
     * @return string
     */
    public function validatedBy(): string
    {
        return $this->validatorClass;
    }
}
