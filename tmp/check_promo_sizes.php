<?php
$i1 = getimagesize('public/images/promotions/promo1.png');
$i2 = getimagesize('public/images/promotions/promo2.png');
$i3 = getimagesize('public/images/promotions/promo3.png');
echo "promo1: {$i1[0]}x{$i1[1]}\n";
echo "promo2: {$i2[0]}x{$i2[1]}\n";
echo "promo3: {$i3[0]}x{$i3[1]}\n";
