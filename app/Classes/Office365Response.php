<?php 
    namespace App\Classes;
    
    class Office365Response
    {
        public static function getPendingOrders()
        {
            $orders = \Orders::where('status','=','pending')
                    ->count();
                
            return $orders;
        }
    }
?>