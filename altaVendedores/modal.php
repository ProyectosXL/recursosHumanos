<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Coeficiente De Ajuste</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
        /* Estilo para el contenedor de la tabla */
        #tableTemporadaContainer {
            max-height: 400px; /* Establece la altura máxima */
            width: 100%;
            overflow-y: auto;  /* Agrega desplazamiento vertical */
        }
</style>

</head>

<body>

    <div class="modal fade" id="modalTemporadas" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modalDoc">
                <div class="modal-header">
                    <h4 class="modal-title" id="exampleModalLabel"><span id="titleModal"><i class="bi bi-clipboard2-check" aria-hidden="true" style="font-size: 25px;"></i> Resultado proceso</h4></span>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button> 
                </div>
                    <div class="modal-body" id="modalVendedores">
                        <div class="row">
                            <div class="col-12">     <div id="tableTemporadaContainer">
                            <table class="table table-hover table-condensed table-striped text-center" style="" id="tableTemporada">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>SUCURSAL</th>
                                        <th>OBSERVACION</th>
                                        <th></th>

                                    </tr>
                                </thead>
                                <tbody id="tableVendedoresBody">
                                </tbody>
                            </table> 
                        </div>
                       
                   
                    </div>
                    
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.8/umd/popper.min.js" integrity="sha512-TPh2Oxlg1zp+kz3nFA0C5vVC6leG/6mm1z9+mA81MI5eaUVqasPLO8Cuk4gMF4gUfP5etR73rgU/8PNMsSesoQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>



</body>    
<script>
      $(document).ready(function () {
        $('#modalTemporadas').on('hidden.bs.modal', function () {
            location.reload();
        });
      })
     
   
</script>
