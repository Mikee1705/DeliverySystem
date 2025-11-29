<html>
    <head>
        <title>Products</title>
    </head>
        <body>
            <?php $ven_id = $_GET['ven_id']?? null;?>
            <form action ="function.php" method ="post">
            <input type="hidden" name="ven_id" value="<?php echo htmlspecialchars($ven_id); ?>">
                <div class ="container">
                    <label for = "prod_name">Product Name:</label>
                    <input type = "text" id = "prod_name" name = "prod_name">
                </div>
                    <div class ="container">
                    <label for = "prod_name">Product Price:</label>
                    <input type = "number" step = "0.01" id ="price" name ="price">
                </div>
                    <fieldset>
                        <p>Product Availability</p>

                        <input type = "radio" id = "yes" name ="prd_avail" value = "1">
                        <label for = "yes">Available</label>

                        
                        <input type = "radio" id = "no" name ="prd_avail" value = "0">
                        <label for = "no">Unavailable</label>

                    </fieldset>
                    <div class = "container">
                        <label for = "quantity">Input Quantity</label>
                        <input type = "number" id = "quantity" name ="quantity">
                    </div>
                     

                    <button type = "submit" name = "add_product">Add Product</button>
            </form>
            <p><a href = "function.php"><button>View Tables</button></a></p>
        </body>

</html>