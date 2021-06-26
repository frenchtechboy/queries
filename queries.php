<?php
/*
 * A dedicated class to communicate with the database. 
*/
class Queries 
{
  use SQLGenerator, DumpsTrait;

  /** @var PDO $PDO */
  protected $PDO;

  /** @var string $SQL */
  protected $table;

  /** @var array $fields Tables columns. */
  protected $fields = [];

  /** @var array $usersSpecifiedFields Tables columns to work with specified by the end-user. */
  protected $userSpecifiedFields = [];

  const FETCH = 0;
  const FETCH_ALL = 1;


  public function __construct(PDO $PDO) 
  {
    $this->PDO = $PDO;
  }

  /** 
    * Specify the current working-with table for the current instance.
    *
    * @param  string $table  A string that represents the table name
    *
    * @return object
  */
  public function table(string $table) : object
  {
    $this->table = $table;
    $this->get_columns_names($this->table);

    return $this;
  }

  /** 
   * Parse a specified column name to ensure it is always prefixed by the table name.
   * 
   * @param  string $columnName The column name.
   * @param  string $split      Remove the table name from the column name.
   * 
   * @return string             An always prefixed column name.
  */
  public function _arg_column_name(string $columnName, $split = false) : string 
  {
    if(empty($this->table)) 
    {
      throw new Exception(__METHOD__ . ' : you must have specified a table before.');
    }
    // Add the table prefix to the column name.
    if(!$split) 
    {
      if(!preg_match('/' . preg_quote($this->table, '/') . '\.[a-zA-Z0-9_.-]+/', $columnName))
      {
         return $this->table . '.' . $columnName;
      }
      else
      {
         return $columnName;
      }
    }
    // Split mode, the function removes the table prefix from the column name.
    else 
    {
      if(preg_match('/' . preg_quote($this->table, '/') . '\.([a-zA-Z0-9_.-]+)/', $columnName, $matches))
      {
        return $matches[1];
      }
      else 
      { 
        return $columnName;
      }
    }
  }

  /**
   *  Get a list of columns for the specified table
   *
   * @param string $table  The name of the involved table as a string.
   *
   * @return object
   *
   * @throws Exception
  */
  public function get_columns_names(string $table)
  {

    /* A `dummy` table with an id column only has been written, so we can use it with a JOIN
       to fetch any other table columns names */
    $this->fields = @array_keys($this->PDO->query('SELECT *, dummy.id AS dummy_id FROM dummy LEFT JOIN ' . $table . ' ON 1 = 1 LIMIT 1')
                                          ->fetch(PDO::FETCH_ASSOC));

    if(is_null($this->fields))
    {
       throw new Exception(__METHOD__ . ' : Can\'t retrieve list of fields.');
    }

    foreach($this->fields as $key => $field)
    {
       if($field == 'dummy_id')
       {
          unset($this->fields[$key]);
          continue;
       }
       // Use the prefixed notation not to break fields name in case we make a JOIN later.
       $this->fields[$key] = $table . '.' . $field;
    }
    return $this;
  }
  
  /** 
   * Reset the parameters list, the where clause & the user-specified list of fields. 
   * 
   * @return object
  */
  public function reset() : object 
  {
    $this->userSpecifiedFields = [];
    $this->params = [];
    $this->where = [];
    $this->orderBy = [];
    $this->joins = [];
    return $this;
  }
}
// EOF
