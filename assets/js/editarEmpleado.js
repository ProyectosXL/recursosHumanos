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

const updateValue = (element) => {
    if (element.tagName.toLowerCase() === 'div') {
        // Si el elemento es un div, lo convertimos en un input
        const value = element.getAttribute("attr-realValue").trim();
        const input = document.createElement('input');
        input.setAttribute('attr-title', element.getAttribute("attr-title"));
        input.setAttribute("id", element.getAttribute("id"));
        input.type = 'text';
        input.value = value;
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
        div.classList = element.classList;
        div.id = 'sucursalAsignada'
        div.style = element.getAttribute('style');
        div.onclick = function() {
            updateValueSelectSucursal(this);
        };
        div.setAttribute("attr-realValue", idSucursal);
        div.setAttribute("attr-title", element.getAttribute("attr-title"));
        // Reemplazar el select con el div
        element.parentNode.replaceChild(div, element);
    }
}


const updateValueSelectLocalidad = (element) => {


    if (element.tagName.toLowerCase() === 'div') {
        // Si el elemento es un div, lo convertimos en un select
        
        let localidades = JSON.parse(localStorage.getItem('localidades'))
        

        const select = document.createElement('select');
        
        // Crear y agregar opciones al select
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

        localidades.forEach(element => {
            if (element == localidad ) {
                desSucursal =   element;
            }
        });
        const value =  '<span style="color:#969396">'+element.getAttribute("attr-title")+'</span><br>'+desSucursal;
        const div = document.createElement('div');
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
    }
}

const cambiarEstado = (checkbox) => {

    let estado = 'N';
    if(checkbox.checked){
     
        estado = 'S';
    }


    let nroLegajo = document.querySelector('#nroLegajo').textContent.replace('Nro. legajo', '').trim()
    
    $.ajax({

        url: '../Controller/HorarioController.php?accion=cambiarEstado',
        type: 'POST',
        data: {
            nroLegajo: nroLegajo,
            estado: estado
        },
        success: function(response) {
   
        }
    });

}
const guardaCambios = () => {

    let nroLegajo = document.querySelector('#nroLegajo').textContent.replace('Nro. legajo', '').trim()
    let direccion = document.querySelector('#direccion').textContent.replace('Dirección', '').trim()
    let piso = document.querySelector('#piso').textContent.replace('Piso', '').trim()
    let depto = document.querySelector('#depto').textContent.replace('Depto.', '').trim()
    let	localidad = document.querySelector('#localidad').textContent.replace('Localidad', '').replace('*','').trim()
    let codPostal = document.querySelector('#codPostal').textContent.replace('Cod. postal', '').trim()
    let sucursalAsignada = document.querySelector('#sucursalAsignada').getAttribute('attr-realValue')
    let tareaFuente = document.querySelector('#tareaFuente').textContent.replace('Tarea frecuente', '').replace('*','').trim()
    let pais = document.querySelector('#pais').textContent.replace('Pais', '').trim()
    let tipoDeContrato = document.querySelector('#tipoDeContrato').textContent.replace('Tipo de contrato', '').trim()
    let fechaIngreso = document.querySelector('#fechaIngreso').textContent.replace('Fecha de ingreso', '').trim()
    let email = document.querySelector('#email').textContent.replace('Correo electronico', '').trim()
    let telefonoM = document.querySelector('#telefonoM').textContent.replace('Telefono movil', '').trim()
    let telefonoE = document.querySelector('#telefonoE').textContent.replace('Telefono de emergencia', '').trim()

    if(tareaFuente == ''){
        alert('La tarea frecuente no puede estar vacía', 'error');
        document.querySelector('#tareaFuente').querySelector('span').style.color = 'red';
        return 1;
    }

    $.ajax({

        url: '../Controller/HorarioController.php?accion=editarEmpleado',
        type: 'POST',
        data: {
            nroLegajo:nroLegajo,
            direccion: direccion,
            piso: piso,
            depto: depto,
            localidad: localidad,
            codPostal: codPostal,
            sucursalAsignada: sucursalAsignada,
            tareaFuente: tareaFuente,
            pais: pais,
            tipoDeContrato: tipoDeContrato,
            fechaIngreso: fechaIngreso,
            email: email,
            telefonoM: telefonoM,
            telefonoE: telefonoE
        },
        success: function(response) {
            console.log(response)
            Swal.fire({
                icon: 'success',
                title: 'Cambios guardados',
                showConfirmButton: false,
                timer: 1500
            }).then((result) => {
                
                window.location.href = 'controlHorario.php';
            })
        }
    });
}


function toggleMenu() {
    var menu = document.getElementById("dropdownMenu");
    menu.style.display = menu.style.display === "block" ? "none" : "block";
  }

  // Cerrar el menú si se hace clic fuera de él
  window.onclick = function(event) {
    if (!event.target.matches('.bi-three-dots-vertical')) {
      var menu = document.getElementById("dropdownMenu");
      if (menu.style.display === "block") {
        menu.style.display = "none";
      }
    }
   };