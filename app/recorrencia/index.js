const modal = document.querySelector('#modal')

const camposDoFormulario = ['#nome', '#descricao', '#valor', '#data_inicio', '#data_fim']

const atualizarCampoFim = () => {
  const mensal = document.querySelector('#periodicidade')?.value === 'mensal'
  document.querySelector('#linha-data-fim').hidden = !mensal
}

// Sem r = nova recorrência; com r (dataset do botão Editar) = edição
const abrirModal = (r = null) => {

  document.querySelector('#modal-titulo').textContent = r ? 'Editar recorrência' : 'Nova recorrência'
  document.querySelector('#aviso-edicao').hidden = !r

  document.querySelector('#id').value = r?.id ?? ''
  document.querySelector('#nome').value = r?.nome ?? ''
  document.querySelector('#descricao').value = r?.descricao ?? ''
  document.querySelector('#valor').value = r?.valor ?? ''
  document.querySelector('#categoria_id').value = r?.categoriaId ?? ''
  document.querySelector('#periodicidade').value = r?.periodicidade ?? 'unica'
  document.querySelector('#data_inicio').value = r?.dataInicio ?? modal.dataset.hoje
  document.querySelector('#data_fim').value = r?.dataFim ?? ''

  document.querySelector(`input[name="tipo"][value="${r?.tipo ?? 'despesa'}"]`).checked = true

  atualizarCampoFim()
  modal.showModal()
}

// Validação nativa do navegador nos próprios campos (required, min, max, maxlength).
// O servidor valida tudo de novo na classe: aqui é só para avisar quem digita sem esperar a resposta.
const formularioValido = () => {

  // Recorrência única ignora a data final
  if (document.querySelector('#periodicidade').value === 'unica') {
    document.querySelector('#data_fim').value = ''
  }

  // A data final não pode ser anterior à inicial
  document.querySelector('#data_fim').min = document.querySelector('#data_inicio').value

  return camposDoFormulario.every((seletor) => document.querySelector(seletor).reportValidity())
}

// Envia JSON; no sucesso recarrega a lista (renderizada pelo PHP)
const enviar = async (url, dados, erroPadrao) => {

  try {

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(dados)
    })

    const data = await response.json()

    if (data.success) {
      window.location.reload()
      return
    }

    alert(data.message)

  } catch (error) {
    console.error(error)
    alert(erroPadrao)
  }
}

const salvar = () => {

  if (!formularioValido()) {
    return
  }

  const dados = {
    id: Number(document.querySelector('#id')?.value) || 0,
    tipo: document.querySelector('input[name="tipo"]:checked')?.value,
    nome: document.querySelector('#nome')?.value,
    descricao: document.querySelector('#descricao')?.value,
    valor: document.querySelector('#valor')?.value,
    categoria_id: document.querySelector('#categoria_id')?.value,
    periodicidade: document.querySelector('#periodicidade')?.value,
    data_inicio: document.querySelector('#data_inicio')?.value,
    data_fim: document.querySelector('#data_fim')?.value
  }

  enviar('./salva.php', dados, 'Erro ao salvar.')
}

const excluir = (id) => {

  if (!confirm('Apagar esta recorrência?')) {
    return
  }

  enviar('./excluir.php', { id }, 'Erro ao apagar.')
}

document.querySelector('#nova').addEventListener('click', () => abrirModal())
document.querySelector('#salvar').addEventListener('click', salvar)
document.querySelector('#cancelar').addEventListener('click', () => modal.close())
document.querySelector('#periodicidade').addEventListener('change', atualizarCampoFim)

document.querySelectorAll('.editar').forEach((botao) => {
  botao.addEventListener('click', () => abrirModal(botao.dataset))
})

document.querySelectorAll('.excluir').forEach((botao) => {
  botao.addEventListener('click', () => excluir(botao.dataset.id))
})