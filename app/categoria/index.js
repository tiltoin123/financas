const modal = document.querySelector('#modal')

// Sem c = nova categoria; com c (dataset do botão Editar) = edição
const abrirModal = (c = null) => {

  document.querySelector('#modal-titulo').textContent = c ? 'Editar categoria' : 'Nova categoria'
  document.querySelector('#id').value = c?.id ?? ''
  document.querySelector('#nome').value = c?.nome ?? ''

  document.querySelector(`input[name="tipo"][value="${c?.tipo ?? 'despesa'}"]`).checked = true

  modal.showModal()
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

  // Validação nativa do navegador no próprio campo; o servidor valida de novo na classe
  if (!document.querySelector('#nome').reportValidity()) {
    return
  }

  enviar('./salva.php', {
    id: Number(document.querySelector('#id').value) || 0,
    nome: document.querySelector('#nome').value,
    tipo: document.querySelector('input[name="tipo"]:checked')?.value
  }, 'Erro ao salvar.')
}

const excluir = (c) => {

  if (!confirm(`Apagar a categoria "${c.nome}"? Os lançamentos que a usam ficam sem categoria.`)) {
    return
  }

  enviar('./excluir.php', { id: c.id }, 'Erro ao apagar.')
}

document.querySelector('#nova').addEventListener('click', () => abrirModal())
document.querySelector('#salvar').addEventListener('click', salvar)
document.querySelector('#cancelar').addEventListener('click', () => modal.close())

document.querySelectorAll('.editar').forEach((botao) => {
  botao.addEventListener('click', () => abrirModal(botao.dataset))
})

document.querySelectorAll('.excluir').forEach((botao) => {
  botao.addEventListener('click', () => excluir(botao.dataset))
})