<?php
class Product
{
    public $id;
    public $category_id;
    public $name;
    public $description;
    public $price;
    public $currency;
    public $rating;
    public $image_path;
    public $is_active;

    function __construct($data)
    {
        $this->id = (int)$data['id'];
        $this->category_id = (int)$data['category_id'];
        $this->name = $data['name'];
        $this->description = $data['description'];
        $this->price = (float)$data['price'];
        $this->currency = $data['currency'] ?? 'EUR';
        $this->rating = (float)($data['rating'] ?? 0);
        $this->image_path = $data['image_path'] ?? null;
        $this->is_active = (bool)($data['is_active'] ?? true);
    }
}
