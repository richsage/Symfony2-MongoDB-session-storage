<?php

use Symfony\Component\HttpFoundation\SessionStorage\NativeSessionStorage;

/**
 * MongoDBSessionStorage.
 *
 * @author Rich Sage <rich.sage@gmail.com>
 */
class MongoDBSessionStorage extends NativeSessionStorage
{
    protected $db;

    /**
     * Class constructor.
     *
     * @throws InvalidArgumentException if the "collection" option is not provided
     * @throws InvalidArgumentException if the "db_name" option is not provided
     */
    public function __construct(\Mongo $con, array $options = array())
    {
        $options = array_merge(
            array(
                'id_field'   => 'sess_id',
                'data_field' => 'sess_data',
                'time_field' => 'sess_time',
            ),
            $options
        );

        if (!isset($options['db_name'])) {
            throw new \InvalidArgumentException('You must provide the "db_name" option for the MongoDBSessionStorage.');
        }

        if (!isset($options['collection'])) {
            throw new \InvalidArgumentException('You must provide the "collection" option for the MongoDBSessionStorage.');
        }

        parent::__construct($options);

        $this->db = $con->selectDB($options['db_name']);
    }

    /**
     * Starts the session.
     */
    public function start()
    {
        if (self::$sessionStarted) {
            return;
        }

        // use this object as the session handler
        session_set_save_handler(
            array($this, 'sessionOpen'),
            array($this, 'sessionClose'),
            array($this, 'sessionRead'),
            array($this, 'sessionWrite'),
            array($this, 'sessionDestroy'),
            array($this, 'sessionGC')
        );

        parent::start();
    }

    /**
     * Opens a session.
     *
     * @param string $path
     * @param string $name
     *
     * @return bool
     */
    public function sessionOpen($path = null, $name = null)
    {
        return true;
    }

    /**
     * Closes a session.
     *
     * @return bool
     */
    public function sessionClose()
    {
        return true;
    }

    /**
     * Destroys a session.
     *
     * @param string $id The session ID to destroy
     *
     * @return bool
     */
    public function sessionDestroy($id)
    {
        $collection  = $this->options['collection'];
        $id_field = $this->options['id_field'];

        // Remove any records associated with this ID
        return $this->db->selectCollection($collection)->remove(array(
            $id_field => $id
        ));
    }

    /**
     * Cleans up old sessions.
     *
     * @param int $lifetime The lifetime of a session (seconds)
     *
     * @return bool 
     */
    public function sessionGC($lifetime)
    {
        $collection = $this->options['collection'];
        $time_field = $this->options['time_field'];

        // delete any records that are past our expiry
        $this->db->selectCollection($collection)->remove(array(        
            $time_field => array('$lt' => new \MongoDate(time() - $lifetime))
        ));

        return true;
    }

    /**
     * Reads a session.
     *
     * @param string $id A session ID
     *
     * @return string Session data
     */
    public function sessionRead($id)
    {
        // get collection/fields
        $collection = $this->options['collection'];
        $data_field = $this->options['data_field'];
        $id_field = $this->options['id_field'];
        $time_field = $this->options['time_field'];
        $collection = $this->db->selectCollection($collection);
        $result = $collection->findOne(array($id_field => $id));

        if ($result !== null) {
            return $result[$data_field];
        }

        // session does not exist, create it
        $collection->insert(array(
            $id_field => $id,
            $data_field => '',
            $time_field => new \MongoDate(),
            "created_at" => new \MongoDate(),
        ));

        return '';
    }

    /**
     * Writes session data.
     *
     * @param string $id A session ID
     * @param string $data A serialised set of session data
     *
     * @return bool 
     */
    public function sessionWrite($id, $data)
    {
        $collection = $this->options['collection'];
        $data_field = $this->options['data_field'];
        $id_field = $this->options['id_field'];
        $time_field = $this->options['time_field'];

        $collection  = $this->db->selectCollection($collection);

        return $collection->update(
            array(
                $id_field => $id
            ),
            array('$set' => array(
                $data_field => $data,
                $time_field => new \MongoDate()
            ))
        );
    }

    /**
     * Regenerates the session ID
     * Also updates our Mongo record for the existing ID
     *
     * @param bool $destroy
     * @return bool 
     */
    public function regenerate($destroy = false)
    {
        $existingID = $this->getId();

        $collection = $this->options['collection'];
        $id_field   = $this->options['id_field'];
        $time_field = $this->options['time_field'];

        $collection  = $this->db->selectCollection($collection);

        $existingSession = $collection->findOne(array($id_field => $existingID));
      
        parent::regenerate($destroy);

        $newID = $this->getId();

        if ($destroy)
        {
            // Wipe existing session
            $collection->remove(
                array($id_field => $existingID)
            );
        }

        // Seems to be we can only update by specifying a non-updating
        // field to search by - use the original Mongo ID to do this.
        return $collection->update(
            array(
                "_id" => $existingSession["_id"]
            ),
            array('$set' => array(
                $id_field => $newID,
                $time_field => new \MongoDate()
            ))
        );
    }
}
