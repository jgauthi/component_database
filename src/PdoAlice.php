<?php
/*****************************************************************************************************
 * @name PdoAlice
 * @note: Generation of fixtures in database from YAML file (alice) for PHP Legacy
 * @author: Jgauthi <github.com/jgauthi>, created at [12sept2019]
 * @Requirements:
    - Nelmio Alice v3: https://github.com/nelmio/alice
    - PDO instance

     * Example file (one file by table):
        parameters:
            table: user
            pk: id

        AliceItem:
            random_user{0..9}:
                login (unique): <userName()>
                password: <password()>
                email: <safeEmail()>

 ******************************************************************************************************/
namespace Jgauthi\Component\Database;

use DateTimeInterface;
use Nelmio\Alice\Loader\NativeLoader;
use PDO;

class PdoAlice
{
    /** @var NativeLoader */
    private $aliceLoader;
    private $table;
    private $pk;
    /** @var PDO */
    private $db;
    private $fixtures = [];

    /**
     * PdoAlice constructor.
     * @param NativeLoader $aliceLoader
     * @param PDO $pdo
     */
    public function __construct(NativeLoader $aliceLoader, PDO $pdo)
    {
        $this->aliceLoader = $aliceLoader;
        $this->db = $pdo;
    }

    /**
     * @param string $file
     * @return self
     */
    public function loadFile($file)
    {
        $fixtures = $this->aliceLoader->loadFile($file);

        // Set Parameter
        $param = $fixtures->getParameters();
        $this->table = $param['table'];
        $this->pk = ((!empty($param['pk'])) ? $param['pk'] : 'id');

        $this->fixtures = array_values($fixtures->getObjects());

        return $this;
    }

    /**
     * @return self
     */
    public function truncate()
    {
        // Use DELETE instead TRUNCATE for delete data with foreign keys contraints
        $this->db->prepare("DELETE FROM {$this->table} WHERE 1")->execute();
        $this->db->prepare("ALTER TABLE {$this->table} AUTO_INCREMENT = 1")->execute();

        return $this;
    }

    /**
     * @return self
     */
    public function execute()
    {
        if (empty($this->fixtures)) {
            return $this;
        }

        $this->truncate();
        foreach ($this->fixtures as $aliceItem) {
            $bindings = [];
            foreach ($aliceItem as $name => $value) {
                if ($value instanceof DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif (is_array($value) || is_object($value)) {
                    $value = serialize($value);
                }

                $bindings[$name] = $value;
            }

            $keys = array_keys($bindings);
            $fields = '`'.implode('`, `',$keys).'`';

            $placeholder = substr(str_repeat('?,', count($keys)),0,-1);

            $this->db
                ->prepare("INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholder})")
                ->execute(array_values($bindings))
            ;
        }

        return $this;
    }
}
