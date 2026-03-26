$.ajax({
    url: '../Controller/HorarioController.php?accion=sucursales',
    type: 'GET',
    success: function(response) {

        localStorage.setItem('sucursales', response)
    }
})

$.ajax({
    url: '../assets/localidadesArgentina1.json',
    type: 'GET',
    success: function(response) {

        localStorage.setItem('localidades', JSON.stringify(response[0].sinonimos))
    }
})


$.ajax({
    url: '../assets/localidadesUruguay1.json',
    type: 'GET',
    success: function(response) {

        localStorage.setItem('localidadesUy', JSON.stringify(response[0].sinonimos))
    }
})



document.addEventListener('keydown', function(event) {

    if (event.key === 'Tab') {
        event.preventDefault(); 

   

        const elementsArray = ['apellido', 'nombres', 'nroDocumento', 'codVendedor', 'direccion', 'piso', 'depto',
             'pais', 'divLocalidad',
            'codPostal',
             'sucursalAsignada',
              'tareaFuente', 
              'tipoContrato',
             'fechaIngreso', 'email', 'telefono', 'telefonoE'];



        const currentElement = document.activeElement; 

        if(currentElement.tagName.toLowerCase() === 'select'){
            return
        }

        
        let nextElement = null; 

        elementsArray.forEach((element,x) => {
            console.log(currentElement.getAttribute('id'))
            if(element == currentElement.getAttribute('id')){

                if(x == elementsArray.length - 1){

                    nextElement = elementsArray[0];

                }else{

                    nextElement = elementsArray[x+1];
                }
    
            }
        });

        let proxDiv = document.querySelector("#" + nextElement);
        
      
        proxDiv.click();

        let siguienteDiv = document.querySelector("#" + nextElement);
        console.log(proxDiv)
        siguienteDiv.focus();
      
     
    }
});


const updateValue = (element) => {
    if (element.tagName.toLowerCase() === 'div') {
        // Si el elemento es un div, lo convertimos en un input
        // const value = element.textContent.trim();
        const input = document.createElement('input');
        input.setAttribute('attr-title', element.getAttribute("attr-title"));
        input.setAttribute("id", element.getAttribute("id"));
        input.type = 'text';
        input.value = element.getAttribute("attr-realValue");
        // input.value = value;
        input.classList = element.classList;
        input.style = element.getAttribute('style');
        input.onblur = function() {
            updateValue(this);
        };
        element.parentNode.replaceChild(input, element);
        input.focus();
    } else if (element.tagName.toLowerCase() === 'input') {
     
        const value = '<span style="color:#969396">'+element.getAttribute("attr-title")+'</span><br>'+element.value.trim();
        const div = document.createElement('div');
        div.innerHTML = value;
        div.setAttribute("attr-realValue", element.value.trim());
        div.setAttribute("attr-title", element.getAttribute("attr-title"));
        div.setAttribute("id", element.getAttribute("id"));
            
        // Aplicar estilos CSS para controlar el desbordamiento y el texto que sobresale
      
        
        div.classList = element.classList;
        div.style = element.getAttribute('style');
        div.style.overflow = 'hidden'; // Oculta el texto que sobresale
        div.style.textOverflow = 'ellipsis'; // Mostrar puntos suspensivos (...) para indicar que hay más texto pero no se muestra todo
        div.style.whiteSpace = 'nowrap'; // Evita que el texto se envuelva a la siguiente línea
        div.onclick = function() {
            updateValue(this);
        };
        element.parentNode.replaceChild(div, element);
    }
}





const updateValueSelectSucursal = (element) => {
    if (element.tagName.toLowerCase() === 'div') {
        // Si el elemento es un div, lo convertimos en un select
        let sucursales = localStorage.getItem('sucursales')
        let options = JSON.parse(sucursales)
        
        const select = document.createElement('select');
        select.id = "sucursalAsignada"
        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.textContent = option.COD_CLIENT+' - '+option.DESC_SUCURSAL;
            optionElement.value = option.NRO_SUCURSAL;
            if(parseInt(option.NRO_SUCURSAL) == parseInt(element.getAttribute("attr-realValue"))){
                optionElement.selected = true;
            }
            optionElement.setAttribute("attr-realValue", option.NRO_SUCURSAL); 
            select.appendChild(optionElement);
        });
        
        // Seleccionar la opción que coincide con el valor original del div
        const originalValue = element.textContent.trim();
        select.value = originalValue;
        select.onchange = function() {
            updateValueSelectSucursal(this);
        }
      
        select.setAttribute("attr-title", element.getAttribute("attr-title"));
    
        select.classList = element.classList;
        select.style = element.getAttribute('style');
        

        element.parentNode.replaceChild(select, element);

    } else if (element.tagName.toLowerCase() === 'select') {
        
        let idSucursal = element.value;
        let sucursales = JSON.parse(localStorage.getItem('sucursales'))
        let desSucursal = '';
        sucursales.forEach(element => {
            if (parseInt(element.NRO_SUCURSAL) == parseInt(idSucursal)) {
                desSucursal = element.COD_CLIENT+' - '+element.DESC_SUCURSAL;
            }
        });
        const value =  '<span style="color:#969396">'+element.getAttribute("attr-title")+'</span><br>'+desSucursal;
        const div = document.createElement('div');
        div.innerHTML = value;
        div.id = 'sucursalAsignada' ;
        div.classList = element.classList;
        div.style = element.getAttribute('style');
        div.onclick = function() {
            updateValueSelectSucursal(this);
        };
        div.setAttribute("attr-realValue", idSucursal);
        div.setAttribute("attr-title", element.getAttribute("attr-title"));
        // Reemplazar el select con el div
        element.parentNode.replaceChild(div, element);

        document.querySelector("#tareaFuente").click();
        document.querySelector("#tareaFuente").focus();
    }
}


const updateValueSelectLocalidad = (element) => {


    if (element.tagName.toLowerCase() === 'div') {

        
        let localidades = JSON.parse(localStorage.getItem('localidades'))

        const select = document.createElement('select');
        select.id = 'selectLocalidad';
        if (document.querySelector("#pais").textContent == 'URUGUAY') {
            localidades = JSON.parse(localStorage.getItem('localidadesUy'))
        }

        localidades.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.textContent = option;
            optionElement.value = option;
            if(option == element.getAttribute("attr-realValue")){
                optionElement.selected = true;
            }
            optionElement.setAttribute("attr-realValue", option); 
            select.appendChild(optionElement);
        });
        
        // Seleccionar la opción que coincide con el valor original del div
        const originalValue = element.textContent.trim();
        select.value = originalValue;
        select.onchange = function() {
            updateValueSelectLocalidad(this);
        }
      
        select.setAttribute("attr-title", element.getAttribute("attr-title"));
    
        select.classList = element.classList;
        select.style = element.getAttribute('style');
        

        element.parentNode.replaceChild(select, element);

    } else if (element.tagName.toLowerCase() === 'select') {
        let localidad = element.value;
   
        let localidades = JSON.parse(localStorage.getItem('localidades'))

        if (document.querySelector("#pais").textContent == 'URUGUAY') {
            localidades = JSON.parse(localStorage.getItem('localidadesUy'))
        }

        localidades.forEach(element => {
            if (element == localidad ) {
                desSucursal =   element;
            }
        });
        const value =  '<span style="color:#969396">'+element.getAttribute("attr-title")+'</span><br>'+desSucursal;
        const div = document.createElement('div');
        div.id = 'divLocalidad';
        div.innerHTML = value;
        div.classList = element.classList;
        div.style = element.getAttribute('style');
        div.onclick = function() {
            updateValueSelectLocalidad(this);
        };
        div.setAttribute("attr-realValue", localidad);
        div.setAttribute("attr-title", element.getAttribute("attr-title"));


        // Reemplazar el select con el div
        element.parentNode.replaceChild(div, element);

        document.querySelector("#codPostal").click();
        document.querySelector("#codPostal").focus();
    }
}

const updateValueSelectPais = (element) => {

    let paises = ['ARGENTINA', 'URUGUAY'];

    if (element.tagName.toLowerCase() === 'div') {
        

        const select = document.createElement('select');
        

       

        paises.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.textContent = option;
            optionElement.value = option;
            optionElement.setAttribute("attr-realValue", option); 
            select.appendChild(optionElement);
        });
        

        const originalValue = element.textContent.trim();
        select.value = originalValue;
        select.onchange = function() {
            updateValueSelectPais(this);
        }
      
        select.setAttribute("attr-title", element.getAttribute("attr-title"));
    
        select.classList = element.classList;
        select.style = element.getAttribute('style');
        select.id = 'pais';
        

        element.parentNode.replaceChild(select, element);

    } else if (element.tagName.toLowerCase() === 'select') {
        
        let pais = element.value;

        paises.forEach(element => {
            if (element == pais ) {
                desSucursal =   element;
            }
        });
        const value =  '<span style="color:#969396">'+element.getAttribute("attr-title")+'</span><br><span id="pais">'+desSucursal+'</span>';
        const div = document.createElement('div');
        div.innerHTML = value;
        div.classList = element.classList;
        div.style = element.getAttribute('style');
        div.onclick = function() {
            updateValueSelectPais(this);
        };
        div.setAttribute("attr-realValue", pais);
        div.setAttribute("attr-title", element.getAttribute("attr-title"));
        // Reemplazar el select con el div
        element.parentNode.replaceChild(div, element);

        if(document.querySelector("#pais").textContent != ''){
           if(document.querySelector("#selectLocalidad")){

               document.querySelector("#selectLocalidad").innerHTML = '<span style="color:#969396">Localidad <span class="required">*</span> </span>';
           }
           if(  document.querySelector("#divLocalidad")){
               
               document.querySelector("#divLocalidad").setAttribute("onclick", `updateValueSelectLocalidad(this)`);
               document.querySelector("#divLocalidad").classList.add("bordeDiv"); 
               document.querySelector("#divLocalidad").style.backgroundColor ="white";
               document.querySelector("#divLocalidad").click();
               document.querySelector("#selectLocalidad").focus();
               
            } 

            if(document.querySelector("#tipoContrato")){
                // document.querySelector("#tipoContrato").innerHTML = '<span style="color:#969396" >Tipo de contrato <span class="required">*</span></span>';
                document.querySelector("#tipoContrato").textContent = '';
                document.querySelector("#tipoContrato").innerHTML = '<span style="color:#969396" >Tipo de contrato <span class="required">*</span></span>'
            }
            if(document.querySelector("#tipoContrato")){
                document.querySelector("#tipoContrato").setAttribute("onclick", `updateValueSelecTipoContrato(this)`);
                document.querySelector("#tipoContrato").classList.add("bordeDiv"); 
                document.querySelector("#tipoContrato").style.backgroundColor ="white";
            }
    
        }

    }
  
}

const updateValueSelectTareaFrecuente = (element) => {
    const tareas = [
        'CAJERO',
        'CAJERA',
        'ENCARGADA',
        'VENDEDOR',
        'VENDEDORA',
        'SUB ENCARGADO',
        'SUB ENCARGADA'
    ];

    if (element.tagName.toLowerCase() === 'div') {
        const select = document.createElement('select');
        select.id = 'tareaFuente';
        tareas.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.textContent = option;
            optionElement.value = option;
            optionElement.setAttribute('attr-realValue', option);
            if (option === element.getAttribute('attr-realValue')) {
                optionElement.selected = true;
            }
            select.appendChild(optionElement);
        });
        select.value = element.getAttribute('attr-realValue');
        select.onchange = function() {
            updateValueSelectTareaFrecuente(this);
        };
        select.setAttribute('attr-title', element.getAttribute('attr-title'));
        select.classList = element.classList;
        select.style = element.getAttribute('style');
        element.parentNode.replaceChild(select, element);
        select.focus();
    } else if (element.tagName.toLowerCase() === 'select') {
        const tarea = element.value;
        const value = '<span style="color:#969396">' + element.getAttribute('attr-title') + ' <span class="required">*</span></span><br>' + tarea;
        const div = document.createElement('div');
        div.innerHTML = value;
        div.setAttribute('attr-realValue', tarea);
        div.setAttribute('attr-title', element.getAttribute('attr-title'));
        div.setAttribute('id', 'tareaFuente');
        div.classList = element.classList;
        div.style = element.getAttribute('style');
        div.onclick = function() {
            updateValueSelectTareaFrecuente(this);
        };
        element.parentNode.replaceChild(div, element);
        // Opcional: enfocar el siguiente campo si lo deseas
        if(document.querySelector('#tipoContrato')) {
            document.querySelector('#tipoContrato').click();
            document.querySelector('#tipoContrato').focus();
        }
    }
}

const updateValueSelecTipoContrato = (element) => {

    let tipo = ['EFECTIVO', 'TEMPORAL'];
    let pais = document.querySelector("#pais").textContent;

    if(pais == 'ARGENTINA'){
        // si pais es argentina no puede ser efectivo
        tipo = ['TEMPORAL'];

    }

    if (element.tagName.toLowerCase() === 'div') {
        

        const select = document.createElement('select');
        

       

        tipo.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.textContent = option;
            optionElement.value = option;
            optionElement.setAttribute("attr-realValue", option); 
            select.appendChild(optionElement);
        });
        
        // Seleccionar la opción que coincide con el valor original del div
        const originalValue = element.textContent.trim();
        select.value = originalValue;
        select.onchange = function() {
            updateValueSelecTipoContrato(this);
        }
      
        select.setAttribute("attr-title", element.getAttribute("attr-title"));
        select.id = 'tipoContrato'
        select.classList = element.classList;
        select.style = element.getAttribute('style');
        

        element.parentNode.replaceChild(select, element);

    } else if (element.tagName.toLowerCase() === 'select') {
        
        let tipoContrato = element.value;
   
        let siguienteDiv = document.querySelector("#fechaIngreso");

        console.log(tipoContrato)
        if(tipoContrato == 'EFECTIVO'){

        }
        tipo.forEach(element => {
            if (element == tipoContrato ) {
                desSucursal =   element;
            }
        });
        const value =  '<span style="color:#969396">'+element.getAttribute("attr-title")+'</span><br><span id="tipoContrato">'+desSucursal+'</span>';
        const div = document.createElement('div');
        div.innerHTML = value;
        div.classList = element.classList;
        div.style = element.getAttribute('style');
        div.onclick = function() {
            updateValueSelecTipoContrato(this);
        };
        div.setAttribute("attr-realValue", tipoContrato);
        div.setAttribute("attr-title", element.getAttribute("attr-title"));
        // Reemplazar el select con el div
        element.parentNode.replaceChild(div, element);

        updateValueDate(siguienteDiv);

        document.querySelector("#fechaIngreso").focus();

    }
  
}


const updateValueDate = (element) => {

    if (element.tagName.toLowerCase() === 'div') {
        // Si el elemento es un div, lo convertimos en un input
        const value = element.textContent.trim();
        const input = document.createElement('input');
        input.setAttribute('attr-title', element.getAttribute("attr-title"));
        input.type = 'date';
        input.value = value;
        input.id = 'fechaIngreso';
        input.classList = element.classList;
        input.style = element.getAttribute('style');
        input.onblur = function() {
            updateValueDate(this);
        };
        element.parentNode.replaceChild(input, element);
        input.focus();
    } else if (element.tagName.toLowerCase() === 'input') {
     
        const value = '<span style="color:#969396">'+element.getAttribute("attr-title")+'</span><br>'+element.value.trim();
        const div = document.createElement('div');
        div.innerHTML = value;
        div.setAttribute("attr-realValue", element.value.trim());
        div.setAttribute("attr-title", element.getAttribute("attr-title"));
        div.classList = element.classList;
        div.id = 'fechaIngreso'
        div.style = element.getAttribute('style');
        div.onclick = function() {
            updateValueDate(this);
        };
        element.parentNode.replaceChild(div, element);
    }
}


const guardaCambios = () => {


    if(document.querySelector("#apellido").textContent.replace('Apellidos', ' ').replace('*','').trim() == ''){
        alert('El apellido no puede estar vacio', 'error');
        document.querySelector("#apellido").querySelector("span").style.color = 'red';
        return 1;
    }

    if(document.querySelector("#nombres").textContent.replace('Nombres', ' ').replace('*','').trim() == ''){
        alert('El nombre no puede estar vacio', 'error');
        document.querySelector("#nombres").querySelector("span").style.color = 'red';
        return 1;
    }

    if(document.querySelector("#nroDocumento").textContent.replace('Nro. Documento', ' ').replace('*','').trim() == ''){
        alert('El nro de documento no puede estar vacio', 'error');
        document.querySelector("#nroDocumento").querySelector("span").style.color = 'red';
        return 1;
    }


    if(  document.querySelector('[attr-title="Pais"]').selectedIndex  == -1 || document.querySelector("#pais").textContent.replace('Pais', ' ').replace('*','').trim() == ''){
        alert('El pais no puede estar vacio', 'error');
        if(document.querySelector('[attr-title="Pais"]').selectedIndex  == -1 ){
            document.querySelector('[attr-title="Pais"]').style.color = 'red';
        }else{

            document.querySelector("#pais").querySelector("span").style.color = 'red';
        }
        return 1;
    }

    
    if( (document.querySelector('#selectLocalidad') && document.querySelector('#selectLocalidad').selectedIndex == -1 ) ||
     (document.querySelector("#divLocalidad") != null && document.querySelector("#divLocalidad").textContent.replace('Localidad', ' ').replace('*','').trim() == '' )
    ||(document.querySelector("#selectLocalidad") != null && document.querySelector("#selectLocalidad").textContent.replace('Localidad', ' ').replace('*','').trim() == '')){
        alert('La localidad no puede estar vacio', 'error');
        if(document.querySelector("#selectLocalidad") != null &&  document.querySelector("#selectLocalidad").querySelector("span") != null){

            document.querySelector("#selectLocalidad").querySelector("span").style.color = 'red';
        }else{
            if(document.querySelector("#divLocalidad") != null && document.querySelector("#divLocalidad").querySelector("span") != null){
            document.querySelector("#divLocalidad").querySelector("span").style.color = 'red';
            }
        }
        return 1;
    }

    if(document.querySelector('[attr-title="Sucursal asignada"]').selectedIndex  == -1 || document.querySelector("#sucursalAsignada").textContent.replace('Sucursal asignada', ' ').replace('*','').trim() == ''){
        alert('La sucursal no puede estar vacio', 'error');
        if(document.querySelector("#sucursalAsignada")){
            document.querySelector("#sucursalAsignada").querySelector("span").style.color = 'red';
        }
        return 1;
    }

    if(document.querySelector("#tareaFuente").textContent.replace('Tarea frecuente', ' ').replace('*','').trim() == ''){
        alert('La tarea frecuente no puede estar vacio', 'error');
        document.querySelector("#tareaFuente").querySelector("span").style.color = 'red';
        return 1;
    }

    if(document.querySelector("#fechaIngreso").textContent.replace('Fecha de ingreso', ' ').replace('*','').trim() == ''){
        alert('La fecha de ingreso no puede estar vacio', 'error');
        document.querySelector("#fechaIngreso").querySelector("span").style.color = 'red';
        return 1;
    }

    // let nroLegajo = document.querySelector("#nroLegajo").textContent.replace('Nro. legajo ', ' ').trim();
    let apellido = document.querySelector("#apellido").textContent.replace('Apellidos', ' ').trim();
    let nombres = document.querySelector("#nombres").textContent.replace('Nombres', ' ').trim();
    let nroDocumento = document.querySelector("#nroDocumento").textContent.replace('Nro. Documento', ' ').trim();
    let codVendedor = document.querySelector("#codVendedor").textContent.replace('Cod. vend', ' ').trim();
    let direccion = document.querySelector("#direccion").textContent.replace('Direccion', ' ').trim();
    let piso = document.querySelector("#piso").textContent.replace('Piso', ' ').trim();
    let depto = document.querySelector("#depto").textContent.replace('Depto.', ' ').trim();
    let pais = document.querySelector("#pais").textContent.replace('País', ' ').trim();
    let localidad = document.querySelector("#divLocalidad").textContent.replace('Localidad', ' ').trim();
    let codPostal = document.querySelector("#codPostal").textContent.replace('Cod. postal', ' ').trim();
    let sucursal = document.querySelector("#sucursalAsignada").getAttribute("attr-realValue");
    let tareaFuente = document.querySelector("#tareaFuente").textContent.replace('Tarea fuente', ' ').trim();
    let tipoContrato = document.querySelector("#tipoContrato").textContent.replace('Tipo de contrato', ' ').trim();
    let fechaIngreso = document.querySelector("#fechaIngreso").textContent.replace('Fecha de ingreso', ' ').trim();
    let email = document.querySelector("#email").textContent.replace('Correo electronico', ' ').trim();
    let telefono = document.querySelector("#telefono").textContent.replace('Telefono movil', ' ').trim();
    let telefonoE = document.querySelector("#telefonoE").textContent.replace('Telefono de emergencia', ' ').trim();
    let estado = document.querySelector("#flexSwitchCheckDefault").checked; 

    let apellidoYNombre = apellido.toUpperCase() + ', ' + nombres.toUpperCase();


    
    if (estado) {
        estado = 'S'
    }else{
        estado = 'N'
    }

    let legajo = 0;

    if(tipoContrato == 'TEMPORAL' && pais == 'ARGENTINA'){
        legajo = 1;
    }else if(tipoContrato == 'EFECTIVO' && pais == 'URUGUAY'){
        legajo = 2;
    }else if(tipoContrato == 'TEMPORAL' && pais == 'URUGUAY'){
        legajo = 3;
    }else if ( tipoContrato == 'EFECTIVO' && pais == 'ARGENTINA'){
        alert("El contrato efectivo no esta disponible para Argentina", "error");
        return 1;
    }else {
        alert("No se puede crear el empleado con tipo Contrato vacio", "error");
        return 1;
    }
 
    let primerNombre = nombres.split(' ')[0];
    let primeraLetraApellido = apellido.charAt(0).toUpperCase();
    let ultimos3DigitosDocumento = nroDocumento.slice(-3);
    let contraseña = primerNombre.charAt(0).toUpperCase() + primerNombre.slice(1).toLowerCase() + primeraLetraApellido +','+ ultimos3DigitosDocumento;


    let stringParaSql = ""


    stringParaSql = "('"+nroDocumento+"','"+apellido+"','"+nombres+"','"+apellidoYNombre+"', '"+codVendedor+"','"+direccion+"','"+piso+"','"+depto+"','"+pais+"','"+localidad+"','"+codPostal+"','"+sucursal+"','"+tareaFuente+"','"+tipoContrato+"','"+fechaIngreso+"','"+email+"','"+telefono+"','"+telefonoE+"' , '"+estado+"', '"+contraseña+"')";

    $.ajax({
        url: '../Controller/HorarioController.php?accion=crearEmpleado',
        type: 'POST',
        data: {stringParaSql:stringParaSql, legajo:legajo},
        success: function(response) {
            console.log(response)
            if (response == true) {
                alert('Empleado creado correctamente')
                window.location.href = 'controlHorario.php'
            } else {
                alert('Error al crear el empleado')
            }
        }
    })
}