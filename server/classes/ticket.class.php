<?php

include_once(__DIR__.'/../classes/connection.db.php');
include_once(__DIR__.'/../classes/chat.class.php');

class Ticket {

    public $id, $subject, $description, $status, $priority, $tags, $date, $time, $authorID, $assigneeID, $departmentID;

    function __construct($id, $subject, $description, $status, $priority, $tags, $date, $time, $authorID, $assigneeID, $departmentID){
        $this->id = $id;
        $this->subject = $subject;
        $this->description = $description;
        $this->status = $status;
        $this->priority = $priority;
        $this->tags = $tags;
        $this->date = $date;
        $this->time = $time;
        $this->authorID = $authorID;
        $this->assigneeID = $assigneeID;
        $this->departmentID = $departmentID;
    }

    public function matches($status, $priority, $departmentID, $tags){

        foreach($tags as $tag){
            if (array_search($tag, $this->tags) === false){
                return false;
            }
        }

        if ($departmentID != "All" && $this->departmentID != $departmentID){
            return false;
        }
        if ($departmentID == "None" && $this->departmentID != null){
            return false;
        }
        if ($status != "All" && $this->status != $status){
            return false;
        }
        if ($priority != "All" && $this->priority != $priority){
            return false;
        }
        return true;
    }

    public function getChat(){

        return new Chat($this->id);
    }

    public function removeAuthor(){
            
        $db = getDatabaseConnection();

        $query = $db->prepare("UPDATE Ticket SET author = NULL WHERE id = '$this->id'");
        $query->execute();
    }

    static public function getAllTickets(){

        $db = getDatabaseConnection();
    
        $query = $db->prepare("SELECT * FROM Ticket");
        $query->execute();
        
        $results = $query->fetchAll();

        $tickets = array();

        foreach ($results as $row){

            $tags = Ticket::getTagsById($row['id']);

            $tickets[] = new Ticket(
                $row['id'],
                $row['subject'], 
                $row['description'], 
                $row['status'], 
                $row['priority'], 
                $tags, 
                $row['creationDate'], 
                $row['creationTime'], 
                $row['author'], 
                $row['assignee'],
                $row['department']
            );
        }

        return $tickets;
    }

    static public function getTicketByID($ticketID){

        $db = getDatabaseConnection();
    
        $query = $db->prepare("SELECT * FROM Ticket WHERE id = ?");
        $query->execute(array($ticketID));
        
        $results = $query->fetchAll();
    
        $result = $results[0];
    
        $tags = Ticket::getTagsById($result['id']);
    
        $ticket = new Ticket(
            $result['id'],
            $result['subject'], 
            $result['description'], 
            $result['status'], 
            $result['priority'], 
            $tags, 
            $result['creationDate'], 
            $result['creationTime'], 
            $result['author'], 
            $result['assignee'],
            $result['department']
        );
     
        return $ticket;
    }

    static public function getTagsById($id){

        $db = getDatabaseConnection();
    
        $query = $db->prepare("SELECT * FROM Ticket_Hashtag WHERE ticket = '$id'");
        $query->execute();
        
        $results = $query->fetchAll();
    
        $tags = array();
    
        foreach ($results as $row){
    
            $query = $db->prepare("SELECT * FROM Hashtag WHERE id = '$row[hashtag]'");
            $query->execute();
    
            $results = $query->fetchAll();
            $tag = $results[0]['name'];
    
            $tags[] = $tag;
        }
    
        return $tags;
    }

    static public function filterByDepartment($tickets, $departmentID){

        $filteredTickets = array();
    
        foreach ($tickets as $ticket){
            if ($ticket->departmentID == $departmentID){
                $filteredTickets[] = $ticket;
            }
        }
    
        return $filteredTickets;
    }

    static public function removeTicket($id){

        $db = getDatabaseConnection();
    
        $query = $db->prepare("DELETE FROM Ticket WHERE id = ?");
        $query->execute(array($id));
    }

    static public function updateTicket($id, $subject, $description, $priority, $status, $department, $assignee, $tags){

        $db = getDatabaseConnection();

        $query = $db->prepare("UPDATE Ticket SET subject = ?, description = ?, priority = ?, status = ?, department = ?, assignee = ? WHERE id = ?");
        $query->execute(array($subject, $description, $priority, $status, $department, $assignee, $id));

        $query = $db->prepare("DELETE FROM Ticket_Hashtag WHERE ticket = ?");
        $query->execute(array($id));

        foreach ($tags as $tag){

            $query = $db->prepare("SELECT * FROM Hashtag WHERE name = ?");
            $query->execute(array($tag));
            $results = $query->fetchAll();
            if (count($results) == 0){
                $query = $db->prepare("INSERT INTO Hashtag (name) VALUES (?)");
                $query->execute(array($tag));
                $query = $db->prepare("SELECT * FROM Hashtag WHERE name = ?");
                $query->execute(array($tag));
                $results = $query->fetchAll();
            }
            $tagID = $results[0]['id'];
            $query = $db->prepare("INSERT INTO Ticket_Hashtag (ticket, hashtag) VALUES (?, ?)");
            $query->execute(array($id, $tagID));

            Ticket::deleteUnusedHashtags();
        }
    }

    public static function deleteUnusedHashtags(){

        $db = getDatabaseConnection();

        $query = $db->prepare("SELECT * FROM Hashtag");
        $query->execute();
        $results = $query->fetchAll();

        foreach ($results as $result){

            $query = $db->prepare("SELECT * FROM Ticket_Hashtag WHERE hashtag = ?");
            $query->execute(array($result['id']));
            $results = $query->fetchAll();

            if (count($results) == 0){
                $query = $db->prepare("DELETE FROM Hashtag WHERE id = ?");
                $query->execute(array($result['id']));
            }
        }
    }
}

?>