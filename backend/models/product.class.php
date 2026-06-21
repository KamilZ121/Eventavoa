<?php
class Product {
    public $id;
    public $category_id;
    public $name;
    public $description;
    public $price;
    public $currency;
    public $image_path;

    public function __construct($data) {
        $this->id = $data['id'] ?? null;
        $this->category_id = $data['category_id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->price = $data['price'] ?? null;
        $this->currency = $data['currency'] ?? 'EUR';
        $this->image_path = $data['image_path'] ?? null;
    }
}