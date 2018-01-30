<?php
namespace Deskpro\API\GraphQL;

/**
 * Class QueryBuilder
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * @var int
     */
    protected static $tabLength = 4;

    /**
     * @var string
     */
    protected static $regexValidateName = '/^[_a-z]+[_a-z0-9]*$/i';

    /**
     * @var GraphQLClientInterface
     */
    protected $client;

    /**
     * @var string
     */
    protected $operationName;

    /**
     * @var array
     */
    protected $operationArgs = [];

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var string
     */
    protected $cache;

    /**
     * Constructor
     *
     * @param GraphQLClientInterface $client Executes the query
     * @param string $operationName Name of the operation
     * @param array $operationArgs Operation arguments
     */
    public function __construct(GraphQLClientInterface $client, $operationName, array $operationArgs = [])
    {
        $this->client = $client;
        $this->setOperationName($operationName);
        $this->setOperationArgs($operationArgs);
    }

    /**
     * @return string
     */
    public function getOperationName()
    {
        return $this->operationName;
    }

    /**
     * @param string $operationName
     *
     * @return $this
     *
     * @throws Exception\QueryBuilderException
     */
    public function setOperationName($operationName)
    {
        if (!preg_match(self::$regexValidateName, $operationName)) {
            throw new Exception\QueryBuilderException(
                sprintf('Invalid operation name, must match %s', self::$regexValidateName)
            );
        }

        $this->operationName = $operationName;
        $this->cache = null;

        return $this;
    }

    /**
     * @return array
     */
    public function getOperationArgs()
    {
        return $this->operationArgs;
    }

    /**
     * @param array $operationArgs
     *
     * @return $this
     */
    public function setOperationArgs(array $operationArgs)
    {
        $this->operationArgs = $operationArgs;
        $this->cache = null;

        return $this;
    }

    /**
     * @param string $name
     * @param array $args
     * @param array $fields
     * 
     * @return $this
     * 
     * @throws Exception\QueryBuilderException
     */
    public function field($name, $args = [], $fields = [])
    {
        $alias = null;
        if (preg_match('/^(.*?)\s*:\s*(.*?)$/i', $name, $matches)) {
            $alias = $matches[1];
            $name  = $matches[2];
        }

        if (!preg_match(self::$regexValidateName, $name)) {
            throw new Exception\QueryBuilderException(
                sprintf('Invalid field name must match %s', self::$regexValidateName)
            );
        }
        if ($alias && !preg_match(self::$regexValidateName, $alias)) {
            throw new Exception\QueryBuilderException(
                sprintf('Invalid alias must match %s', self::$regexValidateName)
            );
        }

        $this->fields[] = compact('name', 'alias', 'fields', 'args');
        $this->cache = null;

        return $this;
    }

    /**
     * @param array $args
     * @return array
     */
    public function execute(array $args = [])
    {
        return $this->client->execute($this, $args);
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        
        $this->cache = '';
        foreach($this->fields as $values) {
            $this->cache .= $this->buildField($values) . "\n\n";
        }
        
        $this->cache = sprintf(
            "query %s {\n%s\n}",
            $this->buildOperation(),
            rtrim($this->cache)
        );

        return $this->cache;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getQuery();
    }

    /**
     * @param array $values
     * 
     * @return string
     */
    protected function buildField(array $values)
    {
        $name   = $values['name'];
        $alias  = isset($values['alias']) ? $values['alias'] . ': ' : null;
        $args   = $this->buildArgs($values['args']);
        $fields = $this->buildFields($values['fields']);
        if ($fields) {
            $fields = sprintf(
                " {\n%s%s\n%s}",
                $this->tabs(3),
                $fields,
                $this->tabs(1)
            );
        }

        $type = sprintf(
            "%s%s%s(%s)%s",
            $this->tabs(1),
            $alias,
            $name,
            $args,
            $fields
        );

        return $type;
    }

    /**
     * @return string
     */
    protected function buildOperation()
    {
        $operationName = $this->operationName;
        if ($this->operationArgs) {
            $args = [];
            foreach ($this->operationArgs as $name => $type) {
                if ($name[0] !== '$') {
                    $name = '$' . $name;
                }
                $args[] = sprintf('%s: %s', $name, (string)$type);
            }

            $operationName = sprintf('%s (%s)', $operationName, join(', ', $args));
        }
        
        return $operationName;
    }

    /**
     * @param array|string $args
     * 
     * @return string
     */
    protected function buildArgs($args)
    {
        if (is_string($args)) {
            $args = array_map('trim', explode(',', $args));
        }
        
        $sanitizedArgs = [];
        foreach($args as $name => $arg) {
            if (is_integer($name)) {
                list($name, $arg) = array_map('trim', explode(':', $arg));
            }
            $sanitizedArgs[] = sprintf('%s: %s', $name, $arg);
        }
        
        return join(', ', $sanitizedArgs);
    }

    /**
     * @param array|string $fields
     * @param int $indent
     * 
     * @return string
     */
    protected function buildFields($fields, $indent = 3)
    {
        if (is_string($fields)) {
            $fields = array_map('trim', explode(' ', $fields));
        }
        
        $sanitizedFields = [];
        foreach($fields as $name => $field) {
            if (is_array($field)) {
                $indent++;
                $sanitizedFields[] = sprintf(
                    "%s {\n%s%s\n%s}",
                    $name,
                    $this->tabs($indent),
                    $this->buildFields($field, $indent),
                    $this->tabs($indent - 1)
                );
                $indent--;
            } else {
                $sanitizedFields[] = $field;
            }
        }

        return join("\n" . $this->tabs($indent), $sanitizedFields);
    }

    /**
     * @param int $length
     * 
     * @return string
     */
    protected function tabs($length)
    {
        return str_repeat(' ', $length * self::$tabLength);
    }
}