<?php

function hexToRGB ($hex) {
  return list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
}

function degrade($img,$direction,$color1,$color2)
{
        if($direction=='h')
        {
                $size = imagesx($img);
                $sizeinv = imagesy($img);
        }
        else
        {
                $size = imagesy($img);
                $sizeinv = imagesx($img);
        }
        $diffs = array(
                (($color2[0]-$color1[0])/$size),
                (($color2[1]-$color1[1])/$size),
                (($color2[2]-$color1[2])/$size)
        );
        for($i=0;$i<$size;$i++)
        {
                $r = $color1[0]+($diffs[0]*$i);
                $g = $color1[1]+($diffs[1]*$i);
                $b = $color1[2]+($diffs[2]*$i);
                if($direction=='h')
                {
                        imageline($img,$i,0,$i,$sizeinv,imagecolorallocate($img,$r,$g,$b));
                }
                else
                {
                        imageline($img,0,$i,$sizeinv,$i,imagecolorallocate($img,$r,$g,$b));
                }
        }
        return $img;
}
