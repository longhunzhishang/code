<!DOCTYPE html>
<html>
    <head>
        <title>Laravel</title>

        <link href="https://fonts.googleapis.com/css?family=Lato:100" rel="stylesheet" type="text/css">

        <style>
            html, body {
                height: 100%;
            }

            body {
                margin: 0;
                padding: 0;
                width: 100%;
                display: table;
                font-weight: 100;
                font-family: 'Lato';
            }

            .container {
                text-align: center;
                display: table-cell;
                vertical-align: middle;
            }

            .content {
                text-align: center;
                display: inline-block;
            }

            .title {
                font-size: 96px;
            }
        </style>
    </head>
    <body>
<!--         <div class="container">
            <div class="content">
                <form  method="post" class="form-horizontal" enctype="multipart/form-data" action="{{url('/save/excel')}}">
                      <table>
                           
                           <tr> <td>  EXCEL: <input name="file"  type="file" /></td>  </tr>                      
                      </table> 
                <input class="btn btn-primary" value="提交" type="submit" /> 
                </form>
            </div>
        </div>
 -->

         <div class="container">
            <div class="content">
                <form  method="post" class="form-horizontal" enctype="multipart/form-data" action="{{url('/save/app/type')}}">
                      <table>
                           
                           <tr> <td>  EXCEL: <input name="file"  type="file" /></td>  </tr>                      
                      </table> 
                <input class="btn btn-primary" value="提交" type="submit" /> 
                </form>
            </div>
        </div>
    </body>
</html>