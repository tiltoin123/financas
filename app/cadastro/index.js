const cadastra = async () => {
  const nome = document.querySelector('#nome')?.value
  const email = document.querySelector('#email')?.value
  const senha = document.querySelector('#senha')?.value
  const repetirSenha = document.querySelector('#repetir-senha')?.value

  if (!nome || !email || !senha || !repetirSenha) {
    alert('Preencha todos os campos')
    return
  }

  if (document.querySelector('#senha').value.length < 6) {
    alert('A senha deve ter pelo menos 8 digitos.')
    return
  }

  if (document.querySelector('#senha').value !== document.querySelector('#repetir-senha').value) {
    alert('As senhas devem ser iguais.')
    return
  }

  try {
    const response = await fetch('./cadastra.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            nome,
            email,
            senha
        })
    });

    const data = await response.json();

    if (data.success) {
        alert(data.message);
        window.location.href = '../login/index.php';
        return;
    }

    alert(data.message || 'Erro ao cadastrar.');
} catch (error) {
    console.error(error);
    alert('Erro na requisição');
}
}

document.querySelector('#cadastrar').addEventListener('click', cadastra)