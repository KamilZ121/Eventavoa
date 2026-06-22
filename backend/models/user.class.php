<?php
class User
{
    public $id;
    public $anrede;
    public $vorname;
    public $nachname;
    public $email;
    public $benutzername;
    public $rolle;
    public $aktiv;

    function __construct($data)
    {
        $this->id = (int)$data['id'];
        $this->anrede = $data['anrede'] ?? '';
        $this->vorname = $data['vorname'];
        $this->nachname = $data['nachname'];
        $this->email = $data['email'];
        $this->benutzername = $data['benutzername'];
        $this->rolle = $data['rolle'];
        $this->aktiv = (bool)$data['aktiv'];
    }
}
