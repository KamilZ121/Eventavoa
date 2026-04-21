<?php
class Product {
    public $id;
    public $category_id;
    public $sku;
    public $name;
    public $slug;
    public $description;
    public $price;
    public $currency;
    public $stock_quantity;
    public $rating;
    public $image_path;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->category_id = $data['category_id'];
        $this->sku = $data['sku'];
        $this->name = $data['name'];
        $this->slug = $data['slug'];
        $this->description = $data['description'];
        $this->price = $data['price'];
        $this->currency = $data['currency'];
        $this->stock_quantity = $data['stock_quantity'];
        $this->rating = $data['rating'];
        $this->image_path = $data['image_path'];
    }
}