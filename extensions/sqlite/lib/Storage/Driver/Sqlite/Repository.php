<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Extensions\Sqlite\Storage\Driver\Sqlite;

use PhpBench\Expression\Constraint\Constraint;

/**
 * Class for retrieving data from the database.
 */
class Repository
{
    private $manager;
    private $visitor;

    public function __construct(ConnectionManager $manager, ConstraintVisitor $visitor = null)
    {
        $this->manager = $manager;
        $this->visitor = $visitor ?: new ConstraintVisitor();
    }

    public function getIterationRows(Constraint $constraint)
    {
        list($sql, $values) = $this->visitor->visit($constraint);

        $conn = $this->manager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($values);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getRunEnvInformationRows($runId)
    {
        $sql = 'SELECT * FROM environment WHERE run_id = ?';

        $conn = $this->manager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array($runId));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getGroups($subjectId)
    {
        $sql = <<<'EOT'
SELECT 
    name 
    FROM sgroup 
    LEFT JOIN sgroup_subject ON sgroup.id = sgroup_subject.sgroup_id 
    WHERE sgroup_subject.subject_id = ?
EOT;

        $conn = $this->manager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array($subjectId));

        $groups = array_map(function ($value) {
            return $value[0];
        }, $stmt->fetchAll(\PDO::FETCH_NUM));

        return $groups;
    }

    public function getParameters($variantId)
    {
        $sql = <<<'EOT'
SELECT 
    key,
    value
    FROM parameter
    LEFT JOIN variant_parameter ON variant_parameter.parameter_id = parameter.id
    WHERE variant_parameter.variant_id = ?
EOT;

        $conn = $this->manager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array($variantId));

        $parameters = array();
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $data) {
            $parameters[$data['key']] = json_decode($data['value']);
        }

        return $parameters;
    }

    public function getHistoryStatement()
    {
        $sql = <<<'EOT'
SELECT 
    run.id AS run_id, 
    run.date AS run_date,
    run.context AS context,
    environment.value AS vcs_branch
    FROM run
    LEFT OUTER JOIN environment ON environment.provider = "vcs" AND environment.run_id = run.id AND environment.key = "branch"
    ORDER BY run.id DESC
EOT;

        $conn = $this->manager->getConnection();
        $stmt = $conn->query($sql);

        return $stmt;
    }
}