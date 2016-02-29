<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot;

/**
 * Class Conversation
 *
 * Only one conversation can be active at any one time.
 * A conversation is directly linked to a user, chat and the command that is managing the conversation.
 */
class Conversation
{
    /**
     * All information fetched from the database
     *
     * @var array
     */
    protected $conversation = null;

    /**
     * Notes stored inside the conversation
     *
     * @var array
     */
    protected $protected_notes = null;

    /**
     * Notes to be stored
     *
     * @var array
     */
    public $notes = null;

    /**
     * Telegram user id
     *
     * @var int
     */
    protected $user_id;

    /**
     * Telegram chat id
     *
     * @var int
     */
    protected $chat_id;

    /**
     * Command to be executed if the conversation is active
     *
     * @var string
     */
    protected $command;

    /**
     * Conversation contructor to initialize a new conversation
     *
     * @param int    $user_id
     * @param int    $chat_id
     * @param string $command
     */
    public function __construct($user_id, $chat_id, $command = null)
    {
        $this->user_id = $user_id;
        $this->chat_id = $chat_id;
        $this->command = $command;

        //Try to load an existing conversation if possible
        $this->load();
    }

    /**
     * Load the conversation from the database
     *
     * @return bool
     */
    protected function load()
    {
        $this->conversation = null;
        $this->protected_notes = null;
        $this->notes = null;

        //Select an active conversation
        $conversation = ConversationDB::selectConversation($this->user_id, $this->chat_id, 1);
        if (isset($conversation[0])) {
            //Pick only the first element
            $this->conversation = $conversation[0];

            //Load the command from the conversation if it hasn't been passed
            $this->command = $this->command ?: $this->conversation['command'];

            if ($this->command !== $this->conversation['command']) {
                $this->cancel();
                return false;
            }

            //Load the conversation data
            $this->protected_notes = json_decode($this->conversation['data'], true);
            $this->notes = $this->protected_notes;
        }

        return $this->exists();
    }

    /**
     * Check if the conversation already exists
     *
     * @return bool
     */
    public function exists()
    {
        return ($this->conversation !== null);
    }

    /**
     * Start a new conversation if the current command doesn't have one yet
     *
     * @return bool
     */
    protected function start()
    {
        if (!$this->exists() && $this->command) {
            if (ConversationDB::insertConversation(
                $this->user_id,
                $this->chat_id,
                $this->command
            )) {
                return $this->load();
            }
        }

        return false;
    }

    /**
     * Delete the current conversation
     *
     * Currently the Conversation is not deleted but just set to 'stopped'
     *
     * @return bool
     */
    public function stop()
    {
        return $this->updateStatus('stopped');
    }

    /**
     * Cancel the current conversation
     *
     * @return bool
     */
    public function cancel()
    {
        return $this->updateStatus('cancelled');
    }

    /**
     * Update the status of the current conversation
     *
     * @param string $status
     *
     * @return bool
     */
    protected function updateStatus($status)
    {
        if ($this->exists()) {
            $fields = ['status' => $status];
            $where  = [
                'status'  => 'active',
                'user_id' => $this->user_id,
                'chat_id' => $this->chat_id,
            ];
            if (ConversationDB::updateConversation($fields, $where)) {
                //Reload the data
                $this->load();
                return true;
            }
        }

        return false;
    }

    /**
     * Store the array/variable in the database with json_encode() function
     *
     * @param array $data
     *
     * @return bool
     */
    public function update()
    {
        if ($this->exists()) {
            $fields = ['data' => json_encode($this->notes)];
            $where  = [
                'status'  => 'active',
                'user_id' => $this->user_id,
                'chat_id' => $this->chat_id,
            ];
            if (ConversationDB::updateConversation($fields, $where)) {
                //Reload the data
                $this->load();
                return true;
            }
        } elseif ($this->start()) {
            return $this->update();
        }

        return false;
    }

    /**
     * Retrieve the command to execute from the conversation
     *
     * @return string|null
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Retrieve the data stored in the conversation
     *
     * @return array|null
     */
    public function getData()
    {
        return $this->notes;
    }
}
