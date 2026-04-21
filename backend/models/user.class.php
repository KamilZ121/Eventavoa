<?php
class User {
    public $id;
    public $vorname;
    public $nachname;
    public $email;
    public $benutzername;
    public $rolle;
    public $aktiv;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->vorname = $data['vorname'];
        $this->nachname = $data['nachname'];
        $this->email = $data['email'];
        $this->benutzername = $data['benutzername'];
        $this->rolle = $data['rolle'];
        $this->aktiv = $data['aktiv'];
    }
}