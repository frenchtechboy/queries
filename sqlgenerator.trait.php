<?php 
trait SQLGenerator 
{
  private $SQL;
  private $counts = [];
  private $joins = [];
  private $where = [];
  private $orderBy = [];
  private $params = [];
  private $limit = '';
  private $lastInsertId;

  private $paginate = 0;
  private $page;
  private $nbOfElementsPerPage;
 
  /** @var PDOStatement $statement */ 
  private $statement = NULL;
 
  /** @var array $lastFetched The last fetched rows using the fetch() method. */ 
  private $lastFetched = NULL;

  /**
   * Generate a " CREATE TABLE " SQL statement.
   *
   * @return object
  */
  public function createTable()
  {
      $this->SQL = 'CREATE TABLE IF NOT EXISTS ' . $this->table . '(';
      $c = 0;
      foreach($this->userSpecifiedFields as $field) 
      {
        $this->SQL .= (!$c ? "\n":"") . $field . ' ' . $this->types[$c] . (preg_match('/[a-zA-Z_]+\.id/', $field) ? ' AUTO_INCREMENT':'') . ' NOT NULL' . (($c + 1) < count($this->userSpecifiedFields) ? ",\n":'');
        $c++;
      }
      if(in_array($this->table . '.' . 'id', $this->userSpecifiedFields))
      {
        $this->SQL .= ",\nPRIMARY KEY(id)";
      }
      $this->SQL .= ')';
      $this->PDO->exec($this->SQL);
      $this->_dump('sql.dump', $this->SQL);
      return $this;
  }

  /**
   * Generate a SHOW TABLES statement.
   * 
   * @return array An array of strings that represent the list of existing tables. 
  */
  public function showTables() : array 
  {   
      $d = $this->PDO->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
      $res = [];
      foreach($d as $r)
      {
        $res[] = $r[0];
      }
      $this->dump('sql.dump', 'SHOW TABLES');
      return $res;
  }

  /**
   * Truncate the table that is specified as the Queries::$table value. 
   * 
   * @return int
  */
  public function truncate() : int
  {
     $SQL = 'TRUNCATE TABLE ' . $this->table;
     $this->dump('sql.dump', $this->SQL);
     return $this->PDO->exec($this->SQL);
  }
  
  /**
   * Drop the table that is specified as the Queries::$table value. 
   * 
   * @return int
  */
  public function drop() : int
  {
      $this->SQL = 'DROP TABLE ' . $this->table;
      $this->dump('sql.dump', $this->SQL);
      return $this->PDO->exec($this->SQL);
  }

  /** 
   *  Bind a column's value that may be used for the next query 
   * 
   *  @param  string|array $param To-be-bound value's alias name | or an array that 
   *                       represents sets of method's argument.
   * 
   *  @param  string       $value To-be-bound value.
   *  @param  int       $type  To-be-bound value type using a PDO constant's value.
   *
   *  @return object
  */
  public function bind($param, $value = NULL, int $type = 0) : object 
  {
      if(is_array($param))
      {
          foreach($param as $p)
          {
              $this->bind($p[0], $p[1], $p[2]);
          }
      }
      else 
      {
          $param =  ':' . ltrim($param, ':');
          switch($type) 
          {
            case PDO::PARAM_BOOL:
            case PDO::PARAM_NULL:
            case PDO::PARAM_INT:
            case PDO::PARAM_STR:
            case PDO::PARAM_STR_NATL:
            case PDO::PARAM_STR_CHAR:
            break;
          
            default:
              throw new Exception(__METHOD__ . ' invalid $type parameter.');
            break;
          }
          $this->params[$param] = [$param, $value, $type];
        }
      return $this;
  }

  /** 
   * Unbind a parameter's value. 
   * 
   * @param  string $param To-be-removed param name. 
   * 
   * @return object 
  */
  public function unBind(string $param) : object 
  {
      if(!empty($this->params[$param]))
      {
         unset($this->params[$param]);
      }
      else 
      {
         throw new Exception(__METHOD__ . ' : Can\'t unbind not found column value `' . $param . '`');
      }
      return $this;
  }

  /** 
   *  Specify the name of next query's involved columns. 
   * 
   *  @param string|array $name Name of the column to select or a list of columns as an array.
   * 
   *  @return object 
  */
  public function field($name, string $type = '') : object
  {
      if (!is_array($name)) {
          if (!preg_match('/^[a-zA-Z_]+\.[a-zA-Z_]+(?:[ ]+AS[ ]+[a-zA-Z_. ]+)?[ ]*$/', $name)) {
              $name = $this->table . '.' . trim($name); // columns names are prefixed
          }

          $this->userSpecifiedFields[] = $name;

          if (!in_array($name, $this->fields)) {
              // @TODO
              $this->fields[] = $name;
          }
          if (!empty($type)) {
              // @TODO
              $this->types[] = $type;
          }
          return $this;
      }
      else {
          foreach ($name as $column) {
              $this->field($column);
          }
      }
      return $this;
  }

  /** 
   * Generate a SELECT query. 
   * 
   * @param int $fetchFunc Specify whether the function should rely on the PDOStatement::fetch() or the
   *                       PDOStatement::fetchAll() method to get the selected rows.
   * 
   * @return array 
  */
  public function select($fetchFunc = Queries::FETCH_ALL) : ? array 
  {
      $this->statement = NULL;
      $joinedCols = [];
      $joinClause = '';
      $c = 0;
      foreach($this->joins as $joined) 
      {
        [$table, $direction, $condition, $columns] = $joined; 
        $joinClause .= $direction . ' JOIN ' . $table . ' ON ' . $condition . (count($this->joins) > ($c + 1) ? " \n":'');
        foreach($columns as $col) 
        {
          $joinedCols[] =  $table . '.' . $col; 
        }
        $c++; 
      }
      $this->SQL = 'SELECT ' // may be only the user specified columns, or all the columns in the other case  
                             . (!empty($this->userSpecifiedFields) ? implode(', ', $this->userSpecifiedFields) : implode(', ', $this->fields)) 
                             . (!empty($joinedCols) ?                ', ' . implode(', ', $joinedCols):'')

                             . "\n FROM "  . $this->table
                           
                             . (!empty($joinClause) ? "\n" . $joinClause:'');
      if(!empty($this->where)) 
      {
          // Where
          $this->_process_where_clause();
      }

      // Order By
      if(!empty($this->orderBy))
      {
          $this->SQL .= ' ORDER BY ' . implode(', ', $this->orderBy);
      }

      // Limit
      if(!empty($this->limit))
      {
          $this->SQL .= ' LIMIT ' . $this->limit;
      }

      elseif(empty($this->limit) && $this->paginate)
      {
          $offset = ($this->page - 1) * $this->nbOfElementsPerPage;
          $this->SQL .= ' LIMIT ' . $this->nbOfElementsPerPage . ' OFFSET ' . $offset;
      }

      $this->_dump('sql.dump', $this->SQL);

      if(!empty($this->where)) {
          $this->_bind_where_clause_params();
      }

      if(empty($this->statement))
      {
        $r = $this->PDO->query($this->SQL); 
      }
      else {
        $r = $this->statement->execute();
      }
      $this->lastFetched = empty($this->statement) ? 
                                ($fetchFunc == Queries::FETCH_ALL ? $r->fetchAll(PDO::FETCH_ASSOC) : $r->fetch(PDO::FETCH_ASSOC))
                                 : 
                                ($fetchFunc == Queries::FETCH_ALL ? $this->statement->fetchAll(PDO::FETCH_ASSOC) : $this->statement->fetch(PDO::FETCH_ASSOC));

      empty($this->statement) ? $r->closeCursor():$this->statement->closeCursor();

      return is_array($this->lastFetched) ? $this->lastFetched:[];
  }

  public function paginate(int $page, int $nbOfElementsPerPage) : object
  {
      $this->paginate = 1;
      $this->page = $page;
      $this->nbOfElementsPerPage = $nbOfElementsPerPage;
      return $this;
  }


  /**
   * Return the last selected rows by the way of the Queries::fetch method.
   *
   * @return array|null 
  */ 
  public function fetched() 
  {
      return $this->lastFetched;
  }

  /** 
   * Join another table to a SELECT query.
   *
   * @param  string $table      The table to be join
   * @param  string $direction  JOIN clause direction, assumes 'LEFT', 'RIGHT', 'CROSS' or 'INNER'.
   * @param  array  $columns    String names of the columns to be joined
   * 
   * @return object  
  */
  public function join(string $table, string $direction, string $condition, array $columns = []) : object 
  {
      // @TODO: As the join method is called, give aliases to the LEFT table columns names.
      if(!empty($this->where)) 
      {
         throw new Exception('where() must be called after join().');
      }
      switch($direction) 
      {
         case 'LEFT':
         case 'RIGHT':
         case 'CROSS':
         case 'INNER':
         case 'OUTER':
         case '':


         break;

         default: 
           throw new Exception('Invalid $direction value `' . $direction . '`.'); 
         break;
       }
       switch(1) 
       {
           case(preg_match('/ON[ ]+[a-z._]+[ ]+=[ ]+[a-z._]+/i', $condition)):
               $condition = trim(substr($condition, 2));
           break;
           case (preg_match('/[a-z._]+[ ]+=[ ]+[a-z._]+/i', $condition)):
           break;
           default:
             throw new Exception('Invalid $condition value `' . $condition . '`.');
           break;
       }
       $this->joins[] = [$table, $direction, $condition, $columns]; 
       return $this;
  }

  /**
   * Count records.
   *
   * @param  string $countArg Should be '*', DISTINCT + column name, or a column name.
   * 
   * @return int
  */
  public function count(string $countArg = 'id') : ?int
  {
      $this->statement = NULL;
      $this->SQL = 'SELECT COUNT(' . $countArg .') AS nbr FROM ' . $this->table;
      if(!empty($this->where)) 
      {
        $this->_process_where_clause();
        $this->_bind_where_clause_params();
      }
      $this->_dump('sql.dump', $this->SQL);
      $r = $this->statement->execute();

      $nbr = !empty($this->statement) ? $this->statement->fetch(PDO::FETCH_ASSOC) : $r->fetch(PDO::FETCH_ASSOC);
      !empty($this->statement)        ? $this->statement->closeCursor()           : $r->closeCursor();

      return ($this->lastFetched = $nbr['nbr']);
  }

  /**
   * Specify an ORDER BY clause.
   *
   * @param $clause The clause to-be specified.
   *
   * @return object
  */
  public function orderBy($clause) : object
  {
      if(!is_array($clause)) {
          $clause = trim($clause);
          if (preg_match('/^(ORDER BY)(.+)$/', $clause, $matches)) {
              $clause = trim(end($matches));
          }
          $this->orderBy[] = $clause;
      }
      else
      {
          foreach($clause as $c)
          {
              $this->orderBy($c);
          }
      }
      return $this;
  }

  /**
   * Specify a LIMIT clause.
   *
   * @param string $limit The LIMIT clause.
   *
   * @return object
   */
  public function limit(string $limit) : object
  {
      if(preg_match('/LIMIT(.+)/', $limit, $matches))
      {
          $limit = end($matches);
      }
      $this->limit = $limit;
      return $this;
  }

  /** 
   * Generate an INSERT / UPDATE query 
   * 
   * @return bool 
  */
  public function save($forceUpdate = false) : bool
  {
      $this->statement = NULL;
      if(empty($this->params[':id']) && !$forceUpdate)
      {
			  	//                               INSERT
          $this->SQL = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $this->fields) . ') VALUES (';
          $c = 0;
          foreach($this->fields as $field) 
          {
              $columnName = explode('.', $field)[1];
              $paramName = ltrim($columnName, ':');
              if(empty($this->params[$paramName])) 
              {
                  if($columnName == 'id') 
                  {
                      $this->SQL .= 'NULL, ';
                      $c++;
                      continue;
                  }
                  // throw new Exception(__METHOD__ . ' : No value to be bound to the ' . $field . ' column.');
              }
              $this->SQL .= ':' . $columnName . ( ($c+1) < count($this->fields) ? ', ':'' );
              $c++;
          }
          $this->SQL .= ')';

          $this->statement = $this->PDO->prepare($this->SQL);

          foreach($this->params as $param) 
          {
             [$param, $value, $type] = $param;
             $this->statement->bindValue($param, $value, $type);
          }
          $this->_dump('sql.dump', $this->SQL);
          $d = $this->statement->execute();
          $this->lastInsertId = $this->PDO->lastInsertId();
          return $d;
      }
      else 
      {
			  //                             UPDATE
        $this->SQL = 'UPDATE ' . $this->table . ' SET ';
        // We rely on params that has been set to figure out the columns to be updated
        $c = 0;
        if(empty($this->params)) 
        {
          throw new Exception('No parameter to be bound.');
        }
        // Set params aliases to the query
        foreach($this->params as $param) 
        {
          [$param, $value, $type] = $param;
          $columnName = ltrim($param, ':');
          if($columnName == 'id')
          {
            $c++;
            continue;
          }
          $this->SQL .= $columnName . ' = :' . $columnName;
          if(($c +1) < (count($this->params) - 1))
          {
            $this->SQL .= ', ';
          }
          $c++;
        }
        // Build the where clause & process its params 
        $this->_process_where_clause();
        $this->_bind_where_clause_params();
        // Bind SET clause parameters 
        foreach($this->params as $param) 
        {
          [$param, $value, $type] = $param;
          $param = ltrim($param, ':');
          $this->statement->bindValue($param, $value, $type); 
        }
        $this->_dump('sql.dump', $this->SQL);
        return $this->statement->execute();
      }
  }


  /**
   * Make a delete query. 
   * 
   * @return int|bool Number of deleted lines or false if an error occured. 
  */
  public function delete() 
  {
      $this->statement = NULL;
      $this->SQL = 'DELETE FROM ' . $this->table;
      if(!empty($this->where)) 
      { 
        $this->_process_where_clause();
        $this->_bind_where_clause_params();
      }  
      if(empty($this->statement)) 
      {
        $d = $this->PDO->exec($this->SQL);
      }
      else 
      {
        $d = $this->statement->execute();
      }
      $this->_dump('sql.dump', $this->SQL);
      return $d;
  }


  /** 
   * Specify a WHERE condition at the next query
   * 
   * @param string|array $condition The condition to be appended to the WHERE clause,
   *                                or an array used to make recursive calls. 
   * 
   * @param string       $separator An optionnal OR / AND separator 
   * 
   * @return object 
  */
  public function where($condition, $separator = '') : object 
  {
      if(is_array($condition))
      {
        foreach($condition as $cond)
        {
           $this->where($condition[1], $condition[2]);
        }
      }
      else 
      {
        switch($separator) 
        {
            case  NULL:
            case 'AND':
            case 'OR':
            case '':
            break;

            default:
              throw new Exception(__METHOD__ . ' : invalid $separator argument.');
            break;
        }
        // Would be a non-sense to have several identical condition in a WHERE clause.
        foreach($this->where as $where)
        {
          if($where[0] == $condition)
          {
              return $this;
          }
        }
        $this->where[] = [$condition, $separator];
      }
      return $this;
  }


  /**
   * An helper method to process the WHERE clauses in all types of queries. 
   * 
   * @return void 
  */
  private function _process_where_clause() : void 
  {
      if(!empty($this->where)) 
      {
          $this->SQL .= ' WHERE '; 
          $c = 0;
          foreach($this->where as $condition) 
          {
            [$cond, $sep] = $condition; 
            $this->SQL .= (($c + 1) ? ' ':'') . $cond . ((($c + 2) <= count($this->where)) ? (' ' . $sep ):'');
            $c++;
          }
      }
  }

  private function _bind_where_clause_params()
  {
      // Bind parameters
      if(empty($this->statement))
      {
          $this->statement = $this->PDO->prepare($this->SQL);
      }
      if(!empty($this->params))
      {
          $c = 0;
          foreach($this->where as $condition)
          {
              foreach($this->params as $param)
              {
                  [$param, $value, $type] = $param;
                  if(preg_match('/' . preg_quote($param, '/') . '/', $condition[0]))
                  {
                      // Parameter has been found in the WHERE clause.
                      $this->statement->bindValue($param, $value, $type);
                  }
              }
              $c++;
          }
      }
  }

  /** 
   *  Returns the last composed SQL query
   *
   *  @return string  $SQL the last composed SQL query as a string, or NULL if no request has been composed of.
  */
  public function SQL() : ? string 
  {
    return $this->SQL;
  }

 /**
  * Return the last inserted id
  *
  * @return int
  */
  public function lastInsertId() : int
  {
      return $this->lastInsertId;
  }

}
// EOF 
